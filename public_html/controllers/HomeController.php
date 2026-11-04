<?php
class HomeController extends BaseController {
    public function home() {
        $this->setGlobals();

        $this->view->template = 'index.html';
        $this->view->render();
    }

    public function contact() {
        $this->setGlobals();

        $this->view->template = 'contact.html';
        $this->view->render();
    }

    public function not_found() {
        $this->setGlobals();

        $this->view->template = 'errors/404.html';
        $this->view->render();
    }

    public function internal_error() {
        //$this->setGlobals();

        $this->view->template = 'errors/500.html';
        $this->view->render();
    }

}
