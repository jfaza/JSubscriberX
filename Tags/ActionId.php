<?php

namespace JavidFazaeli\JSubscriberX\Tags;

use ExpressionEngine\Service\Addon\Controllers\Tag\AbstractRoute;

class ActionId extends AbstractRoute
{
    // Example tag: {exp:jsubscriberx:action_id}
    public function process()
    {
        $method = ee()->TMPL->fetch_param('method', 'SubscribeX');

        $aid = ee('Model')->get('Action')
            ->filter('class','Jsubscriberx')
            ->filter('method',$method)
            ->first()
            ->action_id;

        if (!$aid) {
            // If your install definitely uses one specific casing, you can remove the others.
            return '<!-- jsubscriberx:action_url: action not found for '.$method.' -->';
        }
        // if url needed 
        // ee()->functions->fetch_site_index(0,0) . QUERY_MARKER . 'ACT=' . $aid;

        return $aid; 
    }
}
