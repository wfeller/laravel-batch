<?php

namespace WF\Batch\Updater;

use WF\Batch\Settings;

interface Updater
{
    public function performUpdate(Settings $settings, string $column, array $values, array $ids) : void;
}
