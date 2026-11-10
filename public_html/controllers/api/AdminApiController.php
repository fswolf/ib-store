<?php
// POST /api/admin/<section>/<action> : everything the admin pages save or list. admin accounts only
class AdminApiController extends ApiController {
    public function handle() {
        $parts = $this->parts();
        $this->checkOrigin();
        $this->requireAdmin();

        $section = $parts[0] ?? '';
        $action = $parts[1] ?? '';

        switch ($section) {
            case 'products':   $this->products($action, $parts[2] ?? ''); break;
            case 'categories': $this->categories($action); break;
            case 'orders':     $this->orders($action); break;
            case 'accounts':   $this->accounts($action); break;
            case 'settings':   $this->settings($action, $parts[2] ?? ''); break;
            default: Flight::halt(404);
        }
    }

    // -- Tables -- //
    // tablesorter.js posts {request, input, rows, offset, sort, order} and wants {success, total, rows}
    private function table($data, $count, $page) {
        $rows = min(max((int) ($data->rows ?? 10), 1), 200);
        $offset = max((int) ($data->offset ?? 0), 0);
        $search = trim((string) ($data->input ?? ''));
        $sort = (string) ($data->sort ?? '');
        $order = (string) ($data->order ?? 'desc');

        return ['total' => $count($search), 'rows' => $page($rows, $offset, $sort, $order, $search)];
    }

    // -- Products -- //
    private function products($action, $sub) {
        switch ($action) {
            case 'list':
                $this->respond(function() {
                    $data = $this->input();
                    return $this->table($data,
                        fn($search) => ProductsModel::count($search),
                        fn($rows, $offset, $sort, $order, $search) => ProductsModel::getPage($rows, $offset, $sort ?: 'id', $order, $search));
                });
                break;

            // POST {id?, ...FIELDS, csrf_token}
            case 'save':
                $this->respond(function() {
                    $data = $this->input();
                    $this->checkCsrf($data);
                    $product = !empty($data->id) ? ProductsModel::getProduct((int) $data->id) : new ProductsModel();
                    if (!$product) throw new Exception('Product not found');
                    $product->fill($data)->save();
                    return ['message' => 'Saved.', 'id' => (int) $product->id, 'slug' => $product->slug, 'redirect' => empty($data->id) ? '/admin/products/' . $product->id : null];
                });
                break;

            // POST {text, csrf_token}: the description editor's preview, through the same renderer as the product page
            case 'preview':
                $this->respond(function() {
                    $data = $this->input();
                    $this->checkCsrf($data);
                    return ['html' => MarkdownLibrary::render((string) ($data->text ?? ''))];
                });
                break;

            // POST {id, csrf_token}
            case 'delete':
                $this->respond(function() {
                    $data = $this->input();
                    $this->checkCsrf($data);
                    $product = ProductsModel::getProduct((int) ($data->id ?? 0));
                    if (!$product) throw new Exception('Product not found');
                    foreach (ProductImgModel::getAll($product->id) as $image) $this->unlinkImage($image->image);
                    ProductsModel::delete($product->id);
                    return ['message' => 'Deleted.', 'redirect' => '/admin/products'];
                });
                break;

            // multipart POST {product_id, keys (pasted, one per line), file (a .txt, one per line), csrf_token} adds to the
            // product's key pool, either or both. keys/clear {id, csrf_token} as json takes the unused ones out.
            // both answer with the pool's new {unused, total} so the page updates without a reload
            case 'keys':
                if ($sub === 'clear') {
                    $this->respond(function() {
                        $data = $this->input();
                        $this->checkCsrf($data);
                        $product = ProductsModel::getProduct((int) ($data->id ?? 0));
                        if (!$product) throw new Exception('Product not found');
                        $removed = ProductKeysModel::clearUnused($product->id);
                        return $this->keyCounts($product->id, ['message' => number_format($removed) . ' unused ' . ($removed === 1 ? 'key' : 'keys') . ' removed.']);
                    });
                    break;
                }

                $this->respond(function() {
                    // php drops the whole body when it is over post_max_size, which would read as an expired form
                    if (!$_POST && !empty($_SERVER['CONTENT_LENGTH'])) throw new Exception('That is bigger than the server takes (post_max_size ' . ini_get('post_max_size') . '), split the file');
                    $this->checkCsrf((object) $_POST);
                    $product = ProductsModel::getProduct((int) ($_POST['product_id'] ?? 0));
                    if (!$product) throw new Exception('Product not found');

                    $text = (string) ($_POST['keys'] ?? '');
                    $file = $_FILES['file'] ?? null;
                    if ($file && !is_array($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                        if ($file['error'] === UPLOAD_ERR_INI_SIZE) throw new Exception('The file is bigger than the server takes (upload_max_filesize ' . ini_get('upload_max_filesize') . '), split it in two');
                        if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload failed (code ' . $file['error'] . ')');
                        $contents = file_get_contents($file['tmp_name']);
                        if (strpos($contents, "\0") !== false || !mb_check_encoding($contents, 'UTF-8')) throw new Exception('That is not a text file, save the keys as .txt one per line');
                        $text .= "\n" . $contents;
                    }

                    [$added, $twice, $already] = ProductKeysModel::add($product->id, $text);
                    $message = number_format($added) . ' ' . ($added === 1 ? 'key' : 'keys') . ' added.';
                    if ($already) $message .= ' ' . number_format($already) . ' already in the list.';
                    if ($twice) $message .= ' ' . number_format($twice) . ' listed twice.';
                    return $this->keyCounts($product->id, ['message' => $message]);
                });
                break;

            // multipart POST: product_id, image (file), csrf_token. not json like the rest
            case 'image':
                if ($sub === 'delete' || $sub === 'main') {
                    $this->respond(function() use ($sub) {
                        $data = $this->input();
                        $this->checkCsrf($data);
                        $image = ProductImgModel::get((int) ($data->id ?? 0));
                        if (!$image) throw new Exception('Image not found');
                        if ($sub === 'main') {
                            ProductImgModel::setMain($image->id);
                            return ['message' => 'Main image set.'];
                        }
                        ProductImgModel::delete($image->id);
                        $this->unlinkImage($image->image);
                        return ['message' => 'Image removed.'];
                    });
                    break;
                }

                $this->respond(function() {
                    $this->checkCsrf((object) $_POST);
                    $product = ProductsModel::getProduct((int) ($_POST['product_id'] ?? 0));
                    if (!$product) throw new Exception('Product not found');
                    $path = $this->saveUpload($_FILES['image'] ?? null, $product->slug);
                    $image = ProductImgModel::add($product->id, $path);
                    return ['message' => 'Image added.', 'image' => ['id' => (int) $image->id, 'image' => $image->image, 'sort_order' => (int) $image->sort_order]];
                });
                break;

            default: Flight::halt(404);
        }
    }

    // the key pool's counts added to an answer, for the keys section to show
    private function keyCounts($product_id, $result) {
        [$unused, $total] = ProductKeysModel::counts($product_id);
        return $result + ['unused' => $unused, 'total' => $total];
    }

    // checks type by content not extension, names the file after the product slug
    private function saveUpload($file, $slug) {
        if (!$file || !isset($file['error']) || is_array($file['error'])) throw new Exception('No file uploaded');
        if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload failed (code ' . $file['error'] . ')');
        if ($file['size'] > ITEM_IMAGE_MAX) throw new Exception('Image is bigger than ' . round(ITEM_IMAGE_MAX / 1024 / 1024) . ' MB');

        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset($types[$mime])) throw new Exception('Only jpg, png, webp, gif or ico images');

        $dir = $_SERVER['DOCUMENT_ROOT'] . '/' . ITEM_IMAGE_DIR;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new Exception('Cannot create the image folder');

        // slug-1.png, slug-2.png ... first free number
        $n = 1;
        do {
            $name = $slug . '-' . $n++ . '.' . $types[$mime];
        } while (file_exists($dir . $name));

        if (!move_uploaded_file($file['tmp_name'], $dir . $name)) throw new Exception('Could not save the image');
        return ITEM_IMAGE_DIR . $name;
    }

    // only touches files inside the item image folder
    private function unlinkImage($path) {
        if (!str_starts_with($path, ITEM_IMAGE_DIR) || str_contains($path, '..')) return;
        $file = $_SERVER['DOCUMENT_ROOT'] . '/' . $path;
        if (is_file($file)) unlink($file);
    }

    // -- Categories -- //
    private function categories($action) {
        switch ($action) {
            case 'save':
                $this->respond(function() {
                    $data = $this->input();
                    $this->checkCsrf($data);
                    $category = !empty($data->id) ? CategoriesModel::get((int) $data->id) : new CategoriesModel();
                    if (!$category) throw new Exception('Category not found');
                    $category->fill($data)->save();
                    return ['message' => 'Saved.', 'id' => (int) $category->id];
                });
                break;

            case 'delete':
                $this->respond(function() {
                    $data = $this->input();
                    $this->checkCsrf($data);
                    if (!CategoriesModel::delete((int) ($data->id ?? 0))) throw new Exception('Category not found');
                    return ['message' => 'Deleted.'];
                });
                break;

            default: Flight::halt(404);
        }
    }

    // -- Orders -- //
    private function orders($action) {
        switch ($action) {
            // ?status=pending on the endpoint filters the table
            case 'list':
                $this->respond(function() {
                    $data = $this->input();
                    $status = (string) ($_GET['status'] ?? '');
                    return $this->table($data,
                        fn($search) => OrdersModel::count($search, $status),
                        fn($rows, $offset, $sort, $order, $search) => OrdersModel::getPage($rows, $offset, $sort ?: 'created', $order, $search, $status));
                });
                break;

            // POST {input, status}: every order the current search and status filter match, flat for a spreadsheet.
            // export_csv.js turns the rows into the file in the browser
            case 'export':
                $this->respond(function() {
                    $data = $this->input();
                    $search = trim((string) ($data->input ?? ''));
                    $status = (string) ($data->status ?? '');
                    $rows = array();
                    foreach (OrdersModel::getPage(null, 0, 'created', 'desc', $search, $status) as $o) {
                        $rows[] = array(
                            'order' => $o->uuid, 'date' => $o->created, 'status' => $o->status, 'paid_at' => $o->paid_at,
                            'customer' => $o->username, 'email' => $o->email, 'items' => (int) $o->item_count,
                            'subtotal' => $o->subtotal, 'shipping' => $o->shipping, 'tax' => $o->tax, 'total' => $o->total, 'currency' => $o->currency,
                            'payment_method' => $o->payment_method, 'payment_ref' => $o->payment_ref,
                            'bill_name' => trim($o->bill_first_name . ' ' . $o->bill_last_name), 'bill_company' => $o->bill_company, 'bill_phone' => $o->bill_phone,
                            'bill_address' => trim($o->bill_address_1 . ' ' . $o->bill_address_2), 'bill_city' => $o->bill_city, 'bill_province' => $o->bill_province, 'bill_postal_code' => $o->bill_postal_code, 'bill_country' => $o->bill_country,
                            'ship_name' => trim($o->ship_first_name . ' ' . $o->ship_last_name), 'ship_company' => $o->ship_company, 'ship_phone' => $o->ship_phone,
                            'ship_address' => trim($o->ship_address_1 . ' ' . $o->ship_address_2), 'ship_city' => $o->ship_city, 'ship_province' => $o->ship_province, 'ship_postal_code' => $o->ship_postal_code, 'ship_country' => $o->ship_country,
                            'notes' => $o->notes, 'admin_notes' => $o->admin_notes,
                        );
                    }
                    return ['rows' => $rows];
                });
                break;

            // POST {id, status, csrf_token}
            case 'status':
                $this->respond(function() {
                    $data = $this->input();
                    $this->checkCsrf($data);
                    $order = OrdersModel::get((int) ($data->id ?? 0));
                    if (!$order) throw new Exception('Order not found');
                    $from = $order->status;
                    $order->setStatus((string) ($data->status ?? ''));
                    OrderMailLibrary::statusChanged($order, $from, $order->status); // customer hears about paid / shipped / cancelled
                    return ['message' => 'Order marked ' . $order->status . '.', 'status' => $order->status];
                });
                break;

            // POST {id, notes, csrf_token}
            case 'notes':
                $this->respond(function() {
                    $data = $this->input();
                    $this->checkCsrf($data);
                    $order = OrdersModel::get((int) ($data->id ?? 0));
                    if (!$order) throw new Exception('Order not found');
                    $order->saveAdminNotes(mb_substr((string) ($data->notes ?? ''), 0, 5000));
                    return ['message' => 'Notes saved.'];
                });
                break;

            default: Flight::halt(404);
        }
    }

    // -- Accounts -- //
    private function accounts($action) {
        switch ($action) {
            case 'list':
                $this->respond(function() {
                    $data = $this->input();
                    return $this->table($data,
                        fn($search) => AccountsModel::count($search),
                        fn($rows, $offset, $sort, $order, $search) => AccountsModel::getPage($rows, $offset, $sort ?: 'id', $order, $search));
                });
                break;

            // POST {id, username, email, is_admin, verified, notes, csrf_token}
            case 'save':
                $this->respond(function() {
                    $data = $this->input();
                    $this->checkCsrf($data);
                    $account = AccountsModel::getAccount((int) ($data->id ?? 0));
                    if (!$account) throw new Exception('Account not found');

                    $username = AccountsModel::checkUsername($data->username ?? '', $account);
                    $email = AccountsModel::checkEmail($data->email ?? '', $account);

                    // you cannot take admin off yourself, that is how a store ends up with no admin
                    $is_admin = !empty($data->is_admin) ? 1 : 0;
                    if ($account->id == $this->user->id && !$is_admin) throw new Exception('You cannot remove your own admin flag');

                    $account->username = $username;
                    $account->email = $email;
                    $account->is_admin = $is_admin;
                    $account->verified = !empty($data->verified) ? 1 : 0;
                    $account->notes = mb_substr(trim((string) ($data->notes ?? '')), 0, 256) ?: null;
                    $account->saveAdmin();
                    return ['message' => 'Saved.'];
                });
                break;

            default: Flight::halt(404);
        }
    }

    // -- Settings -- //
    private function settings($action, $sub = '') {
        switch ($action) {
            // the home page banner. multipart POST {image, csrf_token} replaces the image, json POST {csrf_token} to
            // hero/delete takes it off, json POST {hero_heading, hero_text, hero_button, hero_link, csrf_token} to
            // hero/save sets the words. its own section in the panel, so its own saves
            case 'hero':
                if ($sub === 'save') {
                    $this->respond(function() {
                        $data = $this->input();
                        $this->checkCsrf($data);
                        (new StoreInfoModel())->fillHero($data)->updateHero();
                        return ['message' => 'Banner saved.'];
                    });
                    break;
                }
                if ($sub === 'delete') {
                    $this->respond(function() {
                        $data = $this->input();
                        $this->checkCsrf($data);
                        $info = StoreInfoModel::getHero();
                        if ($info) $this->unlinkImage($info->hero_image);
                        StoreInfoModel::setHeroImage(null);
                        return ['message' => 'Banner removed.'];
                    });
                    break;
                }

                $this->respond(function() {
                    $this->checkCsrf((object) $_POST);
                    $old = StoreInfoModel::getHero();
                    $path = $this->saveUpload($_FILES['image'] ?? null, 'hero');
                    StoreInfoModel::setHeroImage($path);
                    if ($old) $this->unlinkImage($old->hero_image);
                    return ['message' => 'Banner image saved.', 'image' => $path];
                });
                break;

            // POST {csrf_token}: a test mail to the signed in admin, so the server's mail can be checked from the panel
            case 'testmail':
                $this->respond(function() {
                    $data = $this->input();
                    $this->checkCsrf($data);
                    if (!MailLibrary::enabled()) throw new Exception('Mail is off: MAIL_FROM in config/config.php is empty or php mail() is missing');

                    $info = StoreInfoModel::getContact();
                    $mail = new MailLibrary(['from_name' => $info->store_name]);
                    $mail->send($this->user->email, "{$info->store_name} test email",
                        MailLibrary::card($info->store_name, 'Test email', 'If you are reading this, outgoing mail from ' . htmlspecialchars(base_url()) . ' works.<br>Sent ' . date('M j, Y g:i a') . ' from the admin panel.'), true);
                    return ['message' => 'Sent to ' . $this->user->email . '. Check the spam folder too.'];
                });
                break;

            // POST {<gateway name>: 1 for each box ticked, csrf_token}: which gateways with keys show on the payment page.
            // only those have a box, a gateway without keys keeps whatever it had
            case 'payments':
                $this->respond(function() {
                    $data = $this->input();
                    $this->checkCsrf($data);
                    $off = StoreInfoModel::getPaymentsOff();
                    foreach (PaymentLibrary::all() as $name => $class) {
                        if (!$class::enabled()) continue;
                        $off = array_diff($off, array($name));
                        if (empty($data->{$name})) $off[] = $name;
                    }
                    StoreInfoModel::setPaymentsOff(array_values($off));
                    return ['message' => 'Payments saved.'];
                });
                break;

            // the tab icon, same shape as the banner image: multipart POST {image, csrf_token} to set, favicon/delete to clear
            case 'favicon':
                if ($sub === 'delete') {
                    $this->respond(function() {
                        $data = $this->input();
                        $this->checkCsrf($data);
                        $info = StoreInfoModel::getAll();
                        if ($info && $info->favicon) $this->unlinkImage($info->favicon);
                        StoreInfoModel::setFavicon(null);
                        return ['message' => 'Back to the default icon.'];
                    });
                    break;
                }

                $this->respond(function() {
                    $this->checkCsrf((object) $_POST);
                    $old = StoreInfoModel::getAll();
                    $path = $this->saveUpload($_FILES['image'] ?? null, 'favicon');
                    StoreInfoModel::setFavicon($path);
                    if ($old && $old->favicon) $this->unlinkImage($old->favicon);
                    return ['message' => 'Icon saved.', 'image' => $path];
                });
                break;

            case 'save':
                $this->respond(function() {
                    $data = $this->input();
                    $this->checkCsrf($data);
                    $info = StoreInfoModel::getAll();
                    if (!$info) throw new Exception('store_info has no row, add one in the database first');
                    $info->fill($data)->update();
                    return ['message' => 'Settings saved.'];
                });
                break;

            default: Flight::halt(404);
        }
    }
}
