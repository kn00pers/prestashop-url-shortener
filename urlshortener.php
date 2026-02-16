<?php
/**
 * URL Shortener for PrestaShop
 * ASTRODESIGN.PL - 2026
 * DO WHATEVER YOU WANT WITH THIS MODULE JUST DON'T SELL IT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Urlshortener extends Module
{
    public function __construct()
    {
        $this->name = 'urlshortener';
        $this->tab = 'front_office_features';
        $this->version = '1.0.1';
        $this->author = 'astrodesign.pl';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('URL Shortener');
        $this->description = $this->l('Create short URLs in your own domain');
    }

    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        if (
            !$this->installDatabase()
            || !$this->registerHook('moduleRoutes')
        ) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        return parent::uninstall();
    }

    protected function installDatabase()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'urlshortener_links` (
            `id_urlshortener_link` INT(11) NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(128) NOT NULL,
            `target` TEXT NOT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `clicks` INT(11) NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`id_urlshortener_link`),
            UNIQUE KEY `code_unique` (`code`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        return Db::getInstance()->execute($sql);
    }

    protected function uninstallDatabase()
    {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'urlshortener_links`';
        return Db::getInstance()->execute($sql);
    }

    public function hookModuleRoutes($params)
    {
        return [
            'module-urlshortener-redirect' => [
                'controller' => 'redirect',
                'rule' => 'go/{code}',
                'keywords'   => [
                    'code' => [
                        'regexp' => '[_a-zA-Z0-9\-]+',
                        'param'  => 'code',
                    ],
                ],
                'params'     => [
                    'fc'     => 'module',
                    'module' => $this->name,
                ],
            ],
        ];
    }

    public function getContent()
    {
        $output = '';

        if (Tools::isSubmit('submitUrlshortenerAdd')) {
            $output .= $this->processAddForm();
        } elseif (Tools::isSubmit('deleteUrlshortener') && ($id = (int) Tools::getValue('id_urlshortener_link'))) {
            $output .= $this->processDelete($id);
        }

        $output .= $this->renderForm();
        $output .= $this->renderList();

        return $output;
    }

    protected function processAddForm()
    {
        $code = trim(Tools::getValue('URLSHORTENER_CODE'));
        $target = trim(Tools::getValue('URLSHORTENER_TARGET'));

        if (empty($code) || empty($target)) {
            return $this->displayError($this->l('Code and target URL are required.'));
        }

        if (!preg_match('/^[_a-zA-Z0-9\-]+$/', $code)) {
            return $this->displayError($this->l('The code can only contain letters, numbers, hyphens, and underscores.'));
        }

        if (!Validate::isAbsoluteUrl($target)) {
            return $this->displayError($this->l('The provided URL is invalid.'));
        }

        $exists = Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'urlshortener_links` WHERE `code` = "' . pSQL($code) . '"'
        );

        if ($exists) {
            return $this->displayError($this->l('This code already exists. Please choose another.'));
        }

        $insert = Db::getInstance()->insert('urlshortener_links', [
            'code'     => pSQL($code),
            'target'   => pSQL($target),
            'active'   => 1,
            'clicks'   => 0,
            'date_add' => date('Y-m-d H:i:s'),
        ]);

        if ($insert) {
            return $this->displayConfirmation($this->l('Shortcut saved.'));
        }

        return $this->displayError($this->l('An error occurred while saving the shortcut.'));
    }

    protected function processDelete($id)
    {
        $deleted = Db::getInstance()->delete(
            'urlshortener_links',
            'id_urlshortener_link = ' . (int) $id
        );

        if ($deleted) {
            return $this->displayConfirmation($this->l('Shortcut deleted.'));
        }

        return $this->displayError($this->l('Failed to delete the shortcut.'));
    }

    protected function renderForm()
    {
        $defaultLang = (int) Configuration::get('PS_LANG_DEFAULT');

        $fieldsForm = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Add new URL shortcut'),
                    'icon'  => 'icon-link',
                ],
                'input'  => [
                    [
                        'type'     => 'text',
                        'label'    => $this->l('Shortcut code'),
                        'name'     => 'URLSHORTENER_CODE',
                        'required' => true,
                        'hint'     => $this->l('E.g. "shoes" for address /shoes'),
                    ],
                    [
                        'type'     => 'text',
                        'label'    => $this->l('Target URL'),
                        'name'     => 'URLSHORTENER_TARGET',
                        'required' => true,
                        'hint'     => $this->l('E.g. full search URL'),
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save shortcut'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $defaultLang;
        $helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitUrlshortenerAdd';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->fields_value['URLSHORTENER_CODE'] = Tools::getValue('URLSHORTENER_CODE', '');
        $helper->fields_value['URLSHORTENER_TARGET'] = Tools::getValue('URLSHORTENER_TARGET', '');

        return $helper->generateForm([$fieldsForm]);
    }

    protected function renderList()
    {
        $links = Db::getInstance()->executeS(
            'SELECT * FROM `' . _DB_PREFIX_ . 'urlshortener_links` ORDER BY `id_urlshortener_link` DESC'
        );

        if (!$links) {
            return $this->displayInformation($this->l('No shortcuts defined.'));
        }

        $baseUrl = Tools::getShopDomainSsl(true) . __PS_BASE_URI__;

        $html = '<h3>' . $this->l('Existing shortcuts') . '</h3>';
        $html .= '<div class="table-responsive">';
        $html .= '<table class="table">';
        $html .= '<thead>
            <tr>
                <th>' . $this->l('ID') . '</th>
                <th>' . $this->l('Code') . '</th>
                <th>' . $this->l('Short URL') . '</th>
                <th>' . $this->l('Target URL') . '</th>
                <th>' . $this->l('Clicks') . '</th>
                <th>' . $this->l('Date added') . '</th>
                <th>' . $this->l('Actions') . '</th>
            </tr>
        </thead><tbody>';

        foreach ($links as $link) {
            $shortUrl = $baseUrl . 'go/' . $link['code'];

            $deleteUrl = AdminController::$currentIndex .
                '&configure=' . $this->name .
                '&deleteUrlshortener=1&id_urlshortener_link=' . (int) $link['id_urlshortener_link'] .
                '&token=' . Tools::getAdminTokenLite('AdminModules');

            $html .= '<tr>';
            $html .= '<td>' . (int) $link['id_urlshortener_link'] . '</td>';
            $html .= '<td><code>' . htmlspecialchars($link['code'], ENT_QUOTES, 'UTF-8') . '</code></td>';
            $html .= '<td><a href="' . htmlspecialchars($shortUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank">'
                . htmlspecialchars($shortUrl, ENT_QUOTES, 'UTF-8') . '</a></td>';
            $html .= '<td style="word-break: break-all;">' . htmlspecialchars($link['target'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>' . (int) $link['clicks'] . '</td>';
            $html .= '<td>' . htmlspecialchars($link['date_add'], ENT_QUOTES, 'UTF-8') . '</td>';
            $html .= '<td>
                <a href="' . htmlspecialchars($deleteUrl, ENT_QUOTES, 'UTF-8') . '" class="btn btn-danger btn-xs"
                    onclick="return confirm(\'' . $this->l('Are you sure you want to delete this shortcut?') . '\');">
                    ' . $this->l('Delete') . '
                </a>
            </td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }
}

