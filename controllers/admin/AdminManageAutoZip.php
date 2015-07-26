<?php

/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to arossetti@users.noreply.github.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade AutoZip to newer
 * versions in the future. If you wish to customize AutoZip for your
 * needs please refer to https://github.com/arossetti/Prestashop-Module-AutoZip for more information.
 *
 *  @author    Antonio Rossetti <arossetti@users.noreply.github.com>
 *  @copyright Antonio Rossetti
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  
 */
require_once _PS_MODULE_DIR_.'autozip/classes/AutoZipConfig.php';

class AdminManageAutoZipController extends ModuleAdminController {

    public function __construct() {
        $this->bootstrap = true;
        $this->table = 'autozip';
        $this->explicitSelect = true;
        $this->_join = 'LEFT JOIN `'._DB_PREFIX_.'attachment` at ON a.id_attachment = at.id_attachment '.
            'LEFT JOIN `'._DB_PREFIX_.'product_download` pd ON a.id_product_download = pd.id_product_download ';
        $this->_select = 'at.file_name AS attachment_name, pd.display_filename AS download_name, a.active as zip_active ';
        $this->className = 'AutoZipConfig';
        $this->identifier = 'id_autozip';
        $this->module = 'autozip';
        $this->lang = false;
        $this->requiredDatabase = true;
        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->bulk_actions = array(
            'delete' => array(
                'text' => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected items?'),
                'icon' => 'icon-trash'
            )
        );

        $this->context = Context::getContext();
        parent :: __construct();

        $this->fields_list = array(
            'id_autozip' => array(
                'title' => '#',
            ),
            'attachment_name' => array(
                'title' => $this->module->l('Attachment'),
            ),
            'download_name' => array(
                'title' => $this->module->l('Product Download'),
            ),
            'source_url' => array(
                'title' => $this->module->l('Source Url'),
            ),
            'source_folder' => array(
                'title' => $this->module->l('Source Folder'),
            ),
            'last_zip_update' => array(
                'title' => $this->module->l('Last Zip Date'),
                'type' => 'datetime',
            ),
            'zip_active' => array(
                'title' => $this->module->l('Enabled'),
                'active' => 'status',
            ),
        );
    }

    public function renderList() {

        // if no cron execution within the last weekn we display the reminder as a warning
        if (!(int)Db::getInstance()->getValue('SELECT COUNT(id_autozip) '
                .'FROM `'._DB_PREFIX_.$this->table.'` '
                .'WHERE DATE(last_zip_update) >= DATE_SUB(NOW(),INTERVAL 1 WEEK)')) {
            $this->warnings[] = $this->l('No cron execution detected within the last week').
                $this->module->renderReminder(false);
        }

        return parent::renderList();
    }

    public function postProcess() {
        if ($this->action) {

            if (!$this->tabAccess['add'] === '1')
                $this->errors[] = Tools::displayError('You do not have write access.');

            if (Tools::getIsset('id_attachment') && Tools::getIsset('id_product_download') &&
                (int)Tools::getValue('id_attachment') && (int)Tools::getValue('id_product_download'))
                $this->errors[] = Tools::displayError('You can not choose an attachment and a download at the same time.');

            if (Tools::getIsset('id_attachment') && Tools::getIsset('id_product_download') &&
                !(int)Tools::getValue('id_attachment') && !(int)Tools::getValue('id_product_download'))
                $this->errors[] = Tools::displayError('You have to choose an attachment OR a download.');
        }
        parent::postProcess();
    }

    public function renderForm() {

        $attachement_list = Db::getInstance()->ExecuteS('

        SELECT id_attachment,  file_name '
            .'FROM `'._DB_PREFIX_.'attachment` '
            .(Tools::getIsset('add'.$this->table) ?
                'WHERE id_attachment NOT IN (SELECT id_attachment FROM `'._DB_PREFIX_.$this->table.'`)' : ''));

        $download_list = Db::getInstance()->ExecuteS('SELECT id_product_download, display_filename '
            .'FROM `'._DB_PREFIX_.'product_download` '
            .(Tools::getIsset('add'.$this->table) ?
                'WHERE id_product_download NOT IN (SELECT id_product_download FROM `'._DB_PREFIX_.$this->table.'`)' : ''));

        // No option available
        if (!count($attachement_list) && !count($download_list)) {
            $this->errors[] = Tools::displayError('No attachment or download available (or all already assigned).');
            $this->fields_form = array(
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'hidden'
                )
            );
            return parent::renderForm();
        }

        // "None" option on both list
        $attachement_list[] = array('id_attachment' => 0, 'file_name' => $this->l('(None)'));
        $download_list[] = array('id_product_download' => 0, 'display_filename' => $this->l('(None)'));
        $this->informations[] = Tools::displayError('You have to choose an attachment OR a download.');

        $this->fields_form = array(
            'legend' => array(
                'title' => $this->l('AutoZip'),
            ),
            'input' => array(
                array(
                    'type' => 'select',
                    'label' => $this->l('Product Attachment'),
                    'name' => 'id_attachment',
                    'required' => true,
                    'options' => array(
                        'query' => $attachement_list,
                        'id' => 'id_attachment',
                        'name' => 'file_name',
                    ),
                    'desc' => $this->l('Payable attachment of the virtual product')
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Product Download'),
                    'name' => 'id_product_download',
                    'required' => true,
                    'options' => array(
                        'query' => $download_list,
                        'id' => 'id_product_download',
                        'name' => 'display_filename',
                    ),
                    'desc' => $this->l('Free Download of the virtual product')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Zip Basename : '),
                    'name' => 'zip_basename',
                    'size' => 64,
                    'maxlength' => 96,
                    'required' => true,
                    'desc' => $this->l('Basename used to generate the zip name.').' '.
                    $this->l('If available, the lastest version number will be added at the end of the filename.').
                    ' '.$this->l('Example : "myname" will give "myname-1.2.3.zip"')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Zip Folder : '),
                    'name' => 'zip_folder',
                    'size' => 64,
                    'maxlength' => 255,
                    'required' => false,
                    'desc' => $this->l('Root folder name to have inside the zip (keep empty do disable root folder)')
                ),
                array(
                    'type' => 'radio',
                    'label' => $this->l('Source Type : '),
                    'name' => 'source_type',
                    'required' => true,
                    'desc' => $this->l('Type of the data source / repository'),
                    'values' => array(
                        array(
                            'id' => 'git',
                            'value' => 'git',
                            'label' => 'GIT ( ssh / https )<br/><i>'.
                            $this->l('The script will be able to autodect & checkout the lastest TAG of the repository').'</i>'
                        ),
                        array(
                            'id' => 'svn',
                            'value' => 'svn',
                            'label' => 'SVN ( http / https / ssh )'
                        ),
                        array(
                            'id' => 'wget',
                            'value' => 'wget',
                            'label' => 'File Server ( ftp / http / https ) <br/><i>'.
                            $this->l('If your server is hosting thousand of files, you should specify your '
                                .'subfolder in the "Source Url" AND in the "Source Folder", '
                                .'to avoid downloading no necessary datas.').'</i>'
                        ),
                    )
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Source Url : '),
                    'name' => 'source_url',
                    'size' => 64,
                    'maxlength' => 255,
                    'required' => true,
                    'desc' => $this->l('Base Url of the data source / repository. Example : ').'<br/>'.
                    $this->l('git@github.com: arossetti/Prestashop-Module-AutoZip.git').'<br/>'.
                    $this->l('https: //github.com/arossetti/Prestashop-Module-AutoZip.git').'<br/>'.
                    $this->l('ftp://someserver.net/some/directory').'<br/>'
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Source Login :'),
                    'name' => 'source_login',
                    'size' => 32,
                    'maxlength' => 128,
                    'required' => false,
                    'desc' => $this->l('Login to connect to the source')
                ),
                array(
                    'type' => 'password',
                    'label' => $this->l('Source Password :'),
                    'name' => 'source_password',
                    'size' => 32,
                    'maxlength' => 128,
                    'required' => false,
                    'desc' => $this->l('Password to connect to the source')
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Source Folder :'),
                    'name' => 'source_folder',
                    'size' => 64,
                    'maxlength' => 255,
                    'required' => false,
                    'desc' => $this->l('Subfolder of the data source (relative to the base Url)')
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Enabled :'),
                    'name' => 'active',
                    'required' => true,
                    'desc' => $this->l('Enable or Disable the generation of this zip file'),
                    'is_bool' => true,
                    'values' => array(
                        array(
                            'id' => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')),
                        array(
                            'id' => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    ),
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
            )
        );

        return parent::renderForm();
    }

}
