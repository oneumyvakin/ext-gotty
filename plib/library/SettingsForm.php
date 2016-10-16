<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.

class Modules_Gotty_SettingsForm extends pm_Form_Simple
{
    /**
     * Validate the form
     *
     * @param  array $data
     * @return boolean
     */
    function isValid($data)
    {
        parent::isValid($data);

        $useDomainsCertificate = $this->getElement('useDomainsCertificate')->getValue();
        $portRange = $this->getElement('portRange')->getValue();

        if ($portRange) {
            $matches = [];
            preg_match("/^(\d+)\-(\d+)$/", $portRange, $matches);
            pm_Log::debug(print_r($matches, true));

            if (empty($matches)) {
                $this->getElement('portRange')->addError(pm_Locale::lmsg('wrongPortRange'));
                $this->markAsError();
                return false;
            }

            if ((int)$matches[1] < 1024) {
                $this->getElement('portRange')->addError(pm_Locale::lmsg('wrongPortRangeStart'));
                $this->markAsError();
                return false;
            }
            if ((int)$matches[2] > 65535) {
                $this->getElement('portRange')->addError(pm_Locale::lmsg('wrongPortRangeEnd'));
                $this->markAsError();
                return false;
            }

        }

        return true;
    }
}