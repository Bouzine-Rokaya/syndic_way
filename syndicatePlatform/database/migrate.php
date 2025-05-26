<?php
require_once '../config/database.php';

class DatabaseMigration {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function runMigrations() {
        $migrations = [
            '001_create_subscriptions_table.sql',
            '002_create_utilisateur_table.sql',
            '003_create_syndic_table.sql',
            '004_create_residents_table.sql',
            '005_create_appartement_table.sql',
            '006_create_demandes_maintenance_table.sql',
            '007_create_affectations_maintenance_table.sql',
            '008_create_factures_table.sql',
            '009_create_annonces_table.sql',
            '010_create_menus_table.sql',
            '011_create_role_permissions_table.sql',
            '012_create_syndic_subscriptions_table.sql'
        ];

        foreach ($migrations as $migration) {
            $this->executeMigration($migration);
        }

        $this->seedDefaultData();
        echo "Database migration completed successfully!\n";
    }

    private function executeMigration($filename) {
        $sql = file_get_contents(__DIR__ . '/migrations/' . $filename);
        
        try {
            $this->db->exec($sql);
            echo "Executed: $filename\n";
        } catch (PDOException $e) {
            echo "Error executing $filename: " . $e->getMessage() . "\n";
        }
    }

    private function seedDefaultData() {
        // Insert default subscriptions
        $subscriptions = [
            ['Basic Plan', 29.99, 12, 25, 50],
            ['Professional Plan', 59.99, 12, 100, 200],
            ['Enterprise Plan', 99.99, 12, 500, 1000]
        ];

        $query = "INSERT INTO subscriptions (name, price, duration_months, max_residents, max_apartments) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);

        foreach ($subscriptions as $sub) {
            $stmt->execute($sub);
        }

        // Insert default admin user
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $query = "INSERT INTO utilisateur (nom_complet, email, mot_de_passe, role, must_change_password) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);
        $stmt->execute(['System Administrator', 'admin@syndicate.com', $admin_password, 'admin', 0]);

        // Insert default menus
        $menus = [
            ['Dashboard', 'dashboard', 'dashboard', null, 1],
            ['Users', 'users', 'users', null, 2],
            ['Residents', 'residents', 'users', null, 3],
            ['Apartments', 'apartments', 'building', null, 4],
            ['Maintenance', 'maintenance', 'tools', null, 5],
            ['Invoices', 'invoices', 'money', null, 6],
            ['Announcements', 'announcements', 'megaphone', null, 7],
            ['Reports', 'reports', 'chart', null, 8],
            ['Settings', 'settings', 'cog', null, 9]
        ];

        $query = "INSERT INTO menus (name, slug, icon, parent_id, sort_order) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($query);

        foreach ($menus as $menu) {
            $stmt->execute($menu);
        }

        echo "Default data seeded successfully!\n";
    }
}

// Run migrations
$migration = new DatabaseMigration();
$migration->runMigrations();
?>