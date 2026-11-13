
export const Routes = {
    "/": () => import("./pages/products.js"),
    "/items": () => import("./pages/products.js"),
    "/items/:category": () => import("./pages/products.js"),
    "/search/:item": () => import("./pages/products.js"),
    "/item/:slug": () => import("./pages/product.js"),
    "/contact": () => import("./pages/contact.js"),
    "/cart": () => import("./pages/cart.js"),
    "/cart/checkout": () => import("./pages/shipping.js"),
    "/cart/payment/:order": () => import("./pages/payment.js"),
    "/register": () => import("./pages/register.js"),
    "/signin": () => import("./pages/signin.js"),
    "/forgot": () => import("./pages/forgot.js"),
    "/reset/:token": () => import("./pages/reset.js"),
    "/user/settings": () => import("./pages/profile.js"),
    "/user/orders": () => import("./pages/orders.js"),
    "/user/orders/:uuid": () => import("./pages/orders.js"),
    "/admin/products": () => import("./pages/admin/products.js"),
    "/admin/products/new": () => import("./pages/admin/product.js"),
    "/admin/products/:id": () => import("./pages/admin/product.js"),
    "/admin/categories": () => import("./pages/admin/categories.js"),
    "/admin/orders": () => import("./pages/admin/orders.js"),
    "/admin/orders/:uuid": () => import("./pages/admin/order.js"),
    "/admin/accounts": () => import("./pages/admin/accounts.js"),
    "/admin/settings": () => import("./pages/admin/settings.js")
};

export const Configs = {
    "/": {
        product_lists: [
            {
                element: "featured-items",
                endpoint: "/api/items/featured"
            },
            {
                element: "newest-items",
                endpoint: "/api/items/newest"
            }
        ]
    },
    "/items": {
        product_lists: [
            {
                element: "browse-items",
                endpoint: "/api/items/browse"
            }
        ]
    },
    "/items/:category": {
        product_lists: [
            {
                element: "browse-items",
                endpoint: "/api/items/category"
            }
        ]
    },
    "/search/:item": {
        product_lists: [
            {
                element: "browse-items",
                endpoint: "/api/items/search"
            }
        ]
    },
    "/contact": {
        element: "contact-info",
        endpoint: "/api/store/info"
    },
    "/cart": {
        element: "cart-list",
        endpoint: "/api/cart"
    },
    "/cart/checkout": {
        endpoint: "/api/cart/checkout"
    },
    "/cart/payment/:order": {
        endpoint: "/api/payment"
    },
    "/signin": {
        form: "login-form",
        endpoint: "/api/auth/signin",
    },
    "/register": {
        form: "register-form",
        endpoint: "/api/auth/register",
    },
    "/forgot": {
        form: "forgot-form",
        endpoint: "/api/auth/forgot",
    },
    "/reset/:token": {
        form: "reset-form",
        endpoint: "/api/auth/reset",
    },
    "/user/settings": {
        endpoint: "/api/account"
    },
    "/user/orders": {
        endpoint: "/api/account"
    },
    "/user/orders/:uuid": {
        endpoint: "/api/account"
    },
    "/admin/products": {
        table: "products-table",
        endpoint: "/api/admin/products/list"
    },
    "/admin/products/new": {
        endpoint: "/api/admin/products"
    },
    "/admin/products/:id": {
        endpoint: "/api/admin/products"
    },
    "/admin/categories": {
        form: "category-form",
        endpoint: "/api/admin/categories"
    },
    "/admin/orders": {
        table: "orders-table",
        endpoint: "/api/admin/orders/list"
    },
    "/admin/orders/:uuid": {
        endpoint: "/api/admin/orders"
    },
    "/admin/accounts": {
        table: "accounts-table",
        form: "account-form",
        endpoint: "/api/admin/accounts"
    },
    "/admin/settings": {
        form: "settings-form",
        endpoint: "/api/admin/settings/save"
    }
};
