<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.

class Modules_Gotty_Task_AcquirePort extends pm_LongTask_Task // Since Plesk 17.0
{
    const UID = 'acquire';
    public $trackProgress = false;
    public $hidden = true;

    public function run()
    {
        $sysUser = $this->getParam('sysUser', 'none');
        $subscriptionPath = $this->getParam('subscriptionPath', 'none');
        $gottyMngPath = $this->getParam('gottyMngPath', 'none');
        $timeout = $this->getParam('timeout', '5');
        $portPath = $this->getParam('portPath', 'none');


        $args = [
            $sysUser,
            'exec',
            $subscriptionPath,
            $gottyMngPath,
            '-timeout', $timeout,
            '-acquire-port', $portPath,
        ];
        $err = pm_ApiCli::callSbin('filemng', $args, pm_ApiCli::RESULT_FULL);
        if ($err['code'] <> 0) {
            throw new pm_Exception("Failed to execute gottymng: filemng " . print_r($args, true) . " with: " . print_r($err, true));
        }

        pm_Log::debug('Delete temporary port file');
        pm_ApiCli::callSbin('filemng', [$sysUser, 'rm', $portPath], pm_ApiCli::RESULT_FULL);
        pm_Log::debug('Temporary port file is deleted');
    }

}