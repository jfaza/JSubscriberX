<?php

namespace JavidFazaeli\JSubscriberX\ControlPanel;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractSidebar;

class Sidebar extends AbstractSidebar
{
    public $automatic = false;
    public $header = 'JSubscriberX';

    private $base = 'addons/settings/jsubscriberx/';

    public function process()
    {
        $sidebar = ee('CP/Sidebar')->make();
        $list = $sidebar->addHeader($this->header)->addBasicList();

        $current = ee()->uri->uri_string; // e.g. cp/addons/settings/jsubscriberx/settings
        $mk = fn($suffix) => ee('CP/URL')->make($this->base . $suffix);

        // Optional: keep Index, or remove it if you redirect index -> settings
        $list->addItem('Index', $mk('index'))
            ->withIcon('home')
            ->isActive(strpos($current, $this->base . 'index') !== false);

        $list->addItem('Settings', $mk('settings'))
            ->withIcon('cog')
            ->isActive(strpos($current, $this->base . 'settings') !== false);

        $list->addItem('Test', $mk('test'))
            ->withIcon('sync')
            ->isActive(strpos($current, $this->base . 'test') !== false);

        $list->addItem('Logs', $mk('logs'))
            ->withIcon('list')
            ->isActive(strpos($current, $this->base . 'logs') !== false);

        

        ee()->view->sidebar = $sidebar->render();

    }
}
