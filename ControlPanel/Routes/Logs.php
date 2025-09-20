<?php

namespace JavidFazaeli\JSubscriberX\ControlPanel\Routes;

use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;

class Logs extends AbstractRoute
{
    protected $route_path    = 'logs';
    protected $cp_page_title = 'JSubscriberX Logs';

    public function process($id = false)
    {
        $this->addBreadcrumb('settings', 'Settings');

        $rows = ee()->db->order_by('id', 'DESC')->limit(100)->get('jsubx_logs')->result_array();

        $variables = [
            'rows' => $rows
        ];

        $this->setBody('Logs', $variables);
        return $this;
    }
}
