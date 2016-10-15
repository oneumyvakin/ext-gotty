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
        $port = $this->getElement('port')->getValue();

        if ($useDomainsCertificate) {
            if (isset($result['is_error'])) {

                $msg = $result['message'];

                if ($result['locale_key'] <> '') {
                    $msg = pm_Locale::lmsg($result['locale_key'], $result['locale_args']);
                }

                $this->getElement('useDomainsCertificate')->addError(pm_Locale::lmsg('settingsTestFailed', ['message' => $msg]));
                $this->markAsError();
                return false;
            }
        }

        return true;
    }
}