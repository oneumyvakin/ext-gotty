<?php
// Copyright 1999-2017. Parallels IP Holdings GmbH.

class Modules_Gotty_LongTasks extends pm_Hook_LongTasks  // Since Plesk 17.0
{
    public function getLongTasks()
    {
        return [
            new Modules_Gotty_Task_Execute(),

        ];
    }
}