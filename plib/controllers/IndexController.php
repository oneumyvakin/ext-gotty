<?php
// Copyright 1999-2017. Parallels IP Holdings GmbH.
class IndexController extends pm_Controller_Action
{

    public function indexAction()
    {
        $client = pm_Session::getClient();
        if (!$client->isAdmin()) {
            throw new pm_Exception("Permission denied");
        }

        $this->_forward('settings');
    }

    public function settingsAction()
    {
        $form = new Modules_Gotty_SettingsForm();

        $form->addElement('checkbox', 'useDomainsCertificate', [
            'label' => $this->lmsg('useDomainsCertificate'),
            'value' => pm_Settings::get('useDomainsCertificate'),
        ]);

        $form->addElement('text', 'portRange', [
            'label' => $this->lmsg('portRange'),
            'value' => pm_Settings::get('portStart', '9000') . '-' . pm_Settings::get('portEnd', '10000'),
            'required' => true,
            'validators' => [
                ['NotEmpty', true],
            ],
        ]);

        $form->addControlButtons([
            'cancelLink' => pm_Context::getModulesListUrl(),
        ]);

        if ($this->getRequest()->isPost() && $form->isValid($this->getRequest()->getPost())) {

            pm_Settings::set('useDomainsCertificate', $form->getValue('useDomainsCertificate'));
            preg_match("/^(\d+)\-(\d+)$/", $form->getValue('portRange'), $matches);
            pm_Settings::set('portStart', $matches[1]);
            pm_Settings::set('portEnd', $matches[2]);

            $this->_status->addMessage('info', $this->lmsg('settingsWasSuccessfullySaved'));
            $this->_helper->json(['redirect' => pm_Context::getBaseUrl()]);
        }

        $this->view->form = $form;
    }

    public function shellAction()
    {
        $siteId = $this->_getParam('site_id');
        if (empty($siteId)) {
            throw new pm_Exception("Permission denied");
        }
        if (!pm_Session::getClient()->hasAccessToDomain($siteId)) {
            throw new pm_Exception("Permission denied");
        }

        $domain = pm_Domain::getByDomainId($siteId);
        $subscriptionPath = $domain->getHomePath();
        $gottyStuffPath = $subscriptionPath . '/' . '.web-term';
        $gottyMngBinary = pm_ProductInfo::getOsArch() == 'i386' ? 'gottymng.i386' : 'gottymng.x86_64';
        $gottyMngPath = '/usr/local/psa/admin/sbin/modules/'. pm_Context::getModuleId() . '/' . $gottyMngBinary;
        $sysUser = $domain->getSysUserLogin();
        $shell = pm_Bootstrap::getDbAdapter()->fetchOne("select shell from sys_users where login='${sysUser}'");

        if (!file_exists($gottyStuffPath)) {
            $args = [
                $sysUser,
                'mkdir',
                $gottyStuffPath,
            ];
            $err = pm_ApiCli::callSbin('filemng', $args, pm_ApiCli::RESULT_FULL);
            if ($err['code'] <> 0) {
                throw new pm_Exception("Failed to create folder: filemng " . print_r($args, true) . " with: " . print_r($err, true));
            }
        }
        $tlsCrtPath = $gottyStuffPath . '/' . 'web-term-crt.pem';
        $tlsKeyPath = $gottyStuffPath . '/' . 'web-term-key.pem';
        $certificate = pm_Bootstrap::getDbAdapter()->fetchOne("select cert_file from certificates, hosting where id = certificate_id AND dom_id = ?", [$domain->getId()]);
        pm_Log::debug(print_r($certificate, true));
        if (pm_Settings::get('useDomainsCertificate') && !empty($certificate)) {
            $certRepositoryPath = '/usr/local/psa/var/certificates/';
            $certificatePath = $certRepositoryPath . $certificate;

            $certFiles = [$tlsCrtPath, $tlsKeyPath];
            foreach ($certFiles as $dstFile) {
                $this->copyFile($sysUser, $certificatePath, $dstFile);
            }
        } else {
            $args = [
                $sysUser,
                'exec',
                $subscriptionPath,
                $gottyMngPath,
                '-generate-tls',
                '-crt-file', $tlsCrtPath,
                '-key-file', $tlsKeyPath,
            ];
            $err = pm_ApiCli::callSbin('filemng', $args, pm_ApiCli::RESULT_FULL);
            if ($err['code'] <> 0) {
                throw new pm_Exception("Failed to generate TLS certificate: filemng " . print_r($args, true) . " with: " . print_r($err, true));
            }
        }

        $taskManager = new pm_LongTask_Manager();
        $task = new Modules_Gotty_Task_Execute();
        $task->setParam('sysUser', $sysUser);
        $task->setParam('subscriptionPath', $subscriptionPath);
        $task->setParam('gottyMngPath', $gottyMngPath);
        $task->setParam('timeout', pm_Settings::get('timeout', '5'));
        $task->setParam('tlsCrtPath', $tlsCrtPath);
        $task->setParam('tlsKeyPath', $tlsKeyPath);
        $task->setParam('shell', $shell);
        $task->setParam('configPath', $gottyStuffPath . '/' . '.gotty');
        $gottyBinary = pm_ProductInfo::getOsArch() == 'i386' ? 'gotty.i386' : 'gotty.x86_64';
        $gottyPath = '/usr/local/psa/admin/sbin/modules/'. pm_Context::getModuleId() . '/' . $gottyBinary;
        $task->setParam('gottyPath', $gottyPath);

        $frontEndConfigPath = $gottyStuffPath . '/' . 'front-end.json';
        $this->removeFile($sysUser, $frontEndConfigPath);

        $taskManager->start($task);

        for($i = 1; $i <=10; $i++) {
            sleep(1);
            if (file_exists($frontEndConfigPath)) {
                break;
            }
        }

        $frontEndConfig = file_get_contents($frontEndConfigPath);
        if (!$frontEndConfig) {
            throw new pm_Exception("Failed to read " . $frontEndConfigPath);
        }
        try {
            $frontEnd = json_decode($frontEndConfig, true);
            pm_Log::debug($frontEnd);
        } catch (\Exception $e) {
            throw new pm_Exception("Failed to read " . $frontEndConfigPath . ": " . $e->getMessage());
        }

        $address = $domain->getDisplayName();
        $this->view->address = $address;
        $this->view->port = $frontEnd['Port'];
        $this->view->user = $frontEnd['User'];
        $this->view->pass = $frontEnd['Pass'];

        $this->view->userTitle = pm_Locale::lmsg('user');
        $this->view->passTitle = pm_Locale::lmsg('pass');
    }

    /**
     * @param $sysUser string
     * @param $source string
     * @param $destination string
     * @throws pm_Exception
     */
    private function copyFile($sysUser, $source, $destination)
    {
        $args = [
            $sysUser,
            'touch',
            $destination,
        ];
        $err = pm_ApiCli::callSbin('filemng', $args, pm_ApiCli::RESULT_FULL);
        if ($err['code'] <> 0) {
            throw new pm_Exception("Failed to create file: filemng " . print_r($args, true) . " with: " . print_r($err, true));
        }
        $args = [
            $sysUser,
            'cp2perm',
            $source,
            $destination,
            '640'
        ];
        $err = pm_ApiCli::callSbin('filemng', $args, pm_ApiCli::RESULT_FULL);
        if ($err['code'] <> 0) {
            throw new pm_Exception("Failed to copy file: filemng " . print_r($args, true) . " with: " . print_r($err, true));
        }
    }

    /**
     * @param $sysUser string
     * @param $path string
     * @throws pm_Exception
     */
    private function removeFile($sysUser, $path)
    {
        if (!file_exists($path)) {
            return;
        }
        $args = [
            $sysUser,
            'rm',
            $path,
        ];
        $err = pm_ApiCli::callSbin('filemng', $args, pm_ApiCli::RESULT_FULL);
        if ($err['code'] <> 0) {
            throw new pm_Exception("Failed to remove file: filemng " . print_r($args, true) . " with: " . print_r($err, true));
        }
    }
}
