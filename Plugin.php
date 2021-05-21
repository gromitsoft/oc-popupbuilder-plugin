<?php namespace GromIT\PopupBuilder;

use System\Classes\PluginBase;

/**
 * Popups Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     *
     * @return array
     */
    public function pluginDetails(): array
    {
        return [
            'name'        => 'PopupBuilder',
            'description' => 'Controller behavior for building popups',
            'author'      => 'GromIT',
            'icon'        => 'icon-window-maximize'
        ];
    }
}
