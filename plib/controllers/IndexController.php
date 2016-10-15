<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.
class IndexController extends pm_Controller_Action
{

    public function indexAction()
    {
        $domId = $this->_getParam('dom_id');
        $domain = pm_Domain::getByDomainId($domId);
        $subscriptionPath = $domain->getHomePath();
        $sysUser = $domain->getSysUserLogin();
        $shell = pm_Bootstrap::getDbAdapter()->fetchOne("select shell from sys_users where login='${sysUser}'");
        pm_Log::info(print_r($shell, 1));
        $gottyMngBinary = pm_ProductInfo::getOsArch() == 'i386' ? 'gottymng.i386' : 'gottymng.x86_64';
        $gottyMngPath = '/usr/local/psa/admin/sbin/modules/'. pm_Context::getModuleId() . '/' . $gottyMngBinary;
        $tlsCrtPath = $subscriptionPath . '/' . 'gotty-crt.pem';
        $tlsKeyPath = $subscriptionPath . '/' . 'gotty-key.pem';

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

        $address = '192.168.0.120';
        $port = '9000';
        $user = 'user';
        $pass = 'pass';
        $this->view->address = $address;
        $this->view->port = $port;
        $this->view->user = $user;
        $this->view->pass = $pass;

        $taskManager = new pm_LongTask_Manager();
        $task = new Modules_CustomButtons_Task_Gotty();
        $task->setParam('sysUser', $sysUser);
        $task->setParam('subscriptionPath', $subscriptionPath);
        $task->setParam('shell', $shell);
        $task->setParam('configPath', $this->generateGottyConfig('/usr/local/psa/var/modules/custom-buttons', $port, $tlsCrtPath, $tlsKeyPath, $user, $pass));
        $gottyBinary = pm_ProductInfo::getOsArch() == 'i386' ? 'gotty.i386' : 'gotty.x86_64';
        $gottyPath = '/usr/local/psa/admin/sbin/modules/'. pm_Context::getModuleId() . '/' . $gottyBinary;
        $task->setParam('gottyPath', $gottyPath);

        $taskManager->start($task);

        sleep(3);
    }

    /**
     * @param $configFolder string
     * @param $port string
     * @param $tlsCrtPath string
     * @param $tlsKeyPath string
     * @param $user string
     * @param $pass string
     * @return string
     */
    private function generateGottyConfig($configFolder, $port, $tlsCrtPath, $tlsKeyPath, $user, $pass)
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
        file_put_contents($configPath, $configContent);

        return $configPath;
    }

    public function anotherAction()
    {
    }

}
