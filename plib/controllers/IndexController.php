<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
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
            preg_match("^(\d+)\-(\d+)$", $form->getValue('portRange'), $matches);
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
            $gottyMngBinary = pm_ProductInfo::getOsArch() == 'i386' ? 'gottymng.i386' : 'gottymng.x86_64';
            $gottyMngPath = '/usr/local/psa/admin/sbin/modules/'. pm_Context::getModuleId() . '/' . $gottyMngBinary;

            $args = [
                $sysUser,
                'exec',
                $subscriptionPath,
                $gottyMngPath,
                '-crt-file', $tlsCrtPath,
                '-key-file', $tlsKeyPath,
            ];
            $err = pm_ApiCli::callSbin('filemng', $args, pm_ApiCli::RESULT_FULL);
            if ($err['code'] <> 0) {
                throw new pm_Exception("Failed to generate TLS certificate: filemng " . print_r($args, true) . " with: " . print_r($err, true));
            }
        }

        $address = $domain->getDisplayName();
        $port = $this->getFreePort($sysUser, $subscriptionPath);
        $user = substr(str_shuffle(md5(microtime())), 0, 5);
        $pass = substr(str_shuffle(md5(microtime())), 0, 5);
        $this->view->address = $address;
        $this->view->port = $port;
        $this->view->user = $user;
        $this->view->pass = $pass;

        $this->view->userTitle = pm_Locale::lmsg('user');
        $this->view->passTitle = pm_Locale::lmsg('pass');

        $taskManager = new pm_LongTask_Manager();
        $task = new Modules_Gotty_Task_Execute();
        $task->setParam('sysUser', $sysUser);
        $task->setParam('subscriptionPath', $subscriptionPath);
        $task->setParam('shell', $shell);
        $task->setParam('configPath', $this->generateGottyConfig($sysUser, $gottyStuffPath, $port, $tlsCrtPath, $tlsKeyPath, $user, $pass));
        $gottyBinary = pm_ProductInfo::getOsArch() == 'i386' ? 'gotty.i386' : 'gotty.x86_64';
        $gottyPath = '/usr/local/psa/admin/sbin/modules/'. pm_Context::getModuleId() . '/' . $gottyBinary;
        $task->setParam('gottyPath', $gottyPath);

        $taskManager->start($task);

        sleep(2);
    }

    /**
     * @param $sysUser string
     * @param $configFolder string
     * @param $port string
     * @param $tlsCrtPath string
     * @param $tlsKeyPath string
     * @param $user string
     * @param $pass string
     * @return string
     */
    private function generateGottyConfig($sysUser, $configFolder, $port, $tlsCrtPath, $tlsKeyPath, $user, $pass)
    {
        $configPath = $configFolder . '/' . '.gotty';
        $config = [
            'port' => sprintf('"%s"', $port),
            'enable_tls' => 'true',
            'tls_crt_file' => sprintf('"%s"', $tlsCrtPath),
            'tls_key_file' => sprintf('"%s"', $tlsKeyPath),
            'enable_basic_auth' => 'true',
            'credential' => sprintf('"%s:%s"', $user, $pass),
        ];

        $configContent = '';
        foreach ($config as $param => $value) {
            $configContent .= sprintf("%s = %s\n", $param, $value);
        }
        $tmpConfigPath = '/usr/local/psa/var/modules/'. pm_Context::getModuleId() . '/' . substr(str_shuffle(md5(microtime())), 0, 10);
        file_put_contents($tmpConfigPath, $configContent);
        $this->copyFile($sysUser, $tmpConfigPath, $configPath);

        pm_Log::debug('Delete temporary Gotty config');
        pm_ApiCli::callSbin('filemng', ['psaadm', 'rm', $tmpConfigPath], pm_ApiCli::RESULT_FULL);
        pm_Log::debug('Gotty config is deleted');

        return $configPath;
    }

    /**
     * @param $sysUser
     * @param $subscriptionPath
     * @return mixed
     * @throws pm_Exception
     */
    private function getFreePort($sysUser, $subscriptionPath)
    {
        $gottyMngBinary = pm_ProductInfo::getOsArch() == 'i386' ? 'gottymng.i386' : 'gottymng.x86_64';
        $gottyMngPath = '/usr/local/psa/admin/sbin/modules/'. pm_Context::getModuleId() . '/' . $gottyMngBinary;

        $args = [
            $sysUser,
            'exec',
            $subscriptionPath,
            $gottyMngPath,
            '-get-free-port',
            '-port-start', pm_Settings::get('portStart', '9000'),
            '-port-end', pm_Settings::get('portEnd', '10000'),
        ];
        $err = pm_ApiCli::callSbin('filemng', $args, pm_ApiCli::RESULT_FULL);
        if ($err['code'] <> 0) {
            throw new pm_Exception("Failed to acquire free TCP port: filemng " . print_r($args, true) . " with: " . print_r($err, true));
        }

        return $err['stdout'];
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
}
