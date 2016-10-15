<?php
// Copyright 1999-2016. Parallels IP Holdings GmbH.

class Modules_CustomButtons_LongTasks extends pm_Hook_LongTasks  // Since Plesk 17.0
{
    public function getLongTasks()
    {
        return [
            new Modules_CustomButtons_Task_Gotty(),

        ];
    }
}