<?php


if (!defined('_PS_VERSION_')) {
    exit;
}

class UrlshortenerRedirectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $code = Tools::getValue('code');
        $code = trim((string) $code);

        if ($code === '') {
            $this->notFound();
            return;
        }

        $sql = 'SELECT * FROM `' . _DB_PREFIX_ . 'urlshortener_links`
                WHERE `code` = "' . pSQL($code) . '" AND `active` = 1';
        $link = Db::getInstance()->getRow($sql);

        if (!$link || empty($link['target'])) {
            $this->notFound();
            return;
        }

        Db::getInstance()->execute(
            'UPDATE `' . _DB_PREFIX_ . 'urlshortener_links`
             SET `clicks` = `clicks` + 1
             WHERE `id_urlshortener_link` = ' . (int) $link['id_urlshortener_link']
        );

        header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $link['target']);
        exit;
    }

    protected function notFound()
    {
        header('HTTP/1.0 404 Not Found');
        if (version_compare(_PS_VERSION_, '1.7.0.0', '>=')) {
            $this->setTemplate('errors/404');
        } else {
            Tools::redirect('index.php?controller=404');
        }
    }
}

