<?php

class DotFrmEntryHelper {

    public function getEntryById($entryId) {

        if (class_exists('FrmEntry')) {
            return FrmEntry::getOne($entryId, true);
        }
        return null;

    }

}