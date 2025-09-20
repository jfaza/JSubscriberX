<?php

use ExpressionEngine\Service\Migration\Migration;

class Createactionsubscribexforaddonjsubscriberx extends Migration
{
    /**
     * Execute the migration
     * @return void
     */
    public function up()
    {
        ee('Model')->make('Action', [
            'class' => 'Jsubscriberx',
            'method' => 'SubscribeX',
            'csrf_exempt' => false,
        ])->save();
    }

    /**
     * Rollback the migration
     * @return void
     */
    public function down()
    {
        ee('Model')->get('Action')
            ->filter('class', 'Jsubscriberx')
            ->filter('method', 'SubscribeX')
            ->delete();
    }
}
