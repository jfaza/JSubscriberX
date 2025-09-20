<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use ExpressionEngine\Service\Addon\Installer;

/**
 * JSubscriberX Updater
 *
 * Handles install, uninstall, and update routines for the add-on.
 * Creates and manages database tables for settings and logs.
 *
 * @package    JavidFazaeli\JSubscriberX
 * @author     Javid Fazaeli
 * @license    MIT
 */
class Jsubscriberx_upd extends Installer
{
    /** @var string Whether the add-on has a Control Panel backend */
    public $has_cp_backend = 'y';

    /** @var string Whether the add-on has custom publish fields */
    public $has_publish_fields = 'n';

    /**
     * Constructor.
     * Calls parent constructor for Installer base setup.
     */
    public function __construct()
    {
        parent::__construct(); 
    }

     /**
     * Install routine.
     * Creates the `jsubx_settings` and `jsubx_logs` tables if they do not exist.
     *
     * @return bool TRUE on success
     */
    public function install()
    {
        parent::install();

        ee()->load->dbforge();

        // Create settings table for provider config
        if (!$this->tableExists('jsubx_settings')) {
            ee()->dbforge->add_field([
                'id'         => ['type'=>'INT','constraint'=>10,'unsigned'=>true,'auto_increment'=>true],
                'provider'   => ['type'=>'VARCHAR','constraint'=>50],
                'label'      => ['type'=>'VARCHAR','constraint'=>120,'default'=>'Default'],
                'config_enc' => ['type'=>'TEXT','null'=>true],
                'is_default' => ['type'=>'TINYINT','constraint'=>1,'default'=>1],
                'created_at' => ['type'=>'DATETIME','null'=>true],
                'updated_at' => ['type'=>'DATETIME','null'=>true],
            ]);
            ee()->dbforge->add_key('id', true);
            ee()->dbforge->create_table('jsubx_settings'); // unprefixed; EE adds prefix
        }

        // Create logs table for subscription attempts
        if (!$this->tableExists('jsubx_logs')) {
            ee()->dbforge->add_field([
                'id'            => ['type'=>'INT','constraint'=>10,'unsigned'=>true,'auto_increment'=>true],
                'provider'      => ['type'=>'VARCHAR','constraint'=>50],
                'email'         => ['type'=>'VARCHAR','constraint'=>190],
                'action'        => ['type'=>'VARCHAR','constraint'=>50],
                'http_code'     => ['type'=>'INT','constraint'=>4,'default'=>0],
                'status'        => ['type'=>'VARCHAR','constraint'=>20],
                'payload_json'  => ['type'=>'MEDIUMTEXT','null'=>true],
                'response_json' => ['type'=>'MEDIUMTEXT','null'=>true],
                'created_at'    => ['type'=>'DATETIME','null'=>true],
            ]);
            ee()->dbforge->add_key('id', true);
            ee()->dbforge->create_table('jsubx_logs');
        }

        return true;
    }

    /**
     * Uninstall routine.
     * Drops the add-on's custom tables.
     *
     * @return bool TRUE on success
     */
    public function uninstall()
    {
        parent::uninstall();
        ee()->load->dbforge();
        ee()->dbforge->drop_table('jsubx_settings', true);
        ee()->dbforge->drop_table('jsubx_logs', true);
  
        return true;
    }

    /**
     * Update routine.
     * Stub for handling future version migrations.
     *
     * @param string $current Current installed version
     * @return bool TRUE on success
     */
    public function update($current = '')
    {
        parent::update($current);

        // Future: add ALTER TABLE migrations here if version changes
        return true;
    }

    /**
     * Utility: check if a table exists (prefixed or unprefixed).
     *
     * @param string $table Table name
     * @return bool
     */
    private function tableExists(string $table): bool
    {
        ee()->load->database();
        $db = ee()->db;

        // Works whether you pass prefixed or unprefixed
        if ($db->table_exists($table)) return true;
        return $db->table_exists($db->dbprefix($table));
    }

}
