<?php
/**
 * Syndicate Management Platform - Complete Database Setup Script
 * This script will create the database, tables, and insert default data
 */

echo "<h1>🏢 Syndicate Management Platform - Database Setup</h1>";
echo "<hr>";

try {
    // Database configuration
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $dbname = 'syndicate_platform';

    echo "<h2>📋 Step 1: Database Connection</h2>";

    // Create connection without database first
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    echo "<p>✅ Connected to MySQL server</p>";

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p>✅ Database '$dbname' created</p>";

    // Connect to the new database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");

    echo "<h2>📋 Step 2: Creating Tables</h2>";

    // Table creation queries
    $tables = [
        'utilisateur' => "
        CREATE TABLE IF NOT EXISTS utilisateur (
            id_utilisateur INT PRIMARY KEY AUTO_INCREMENT,
            nom_complet VARCHAR(100) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            mot_de_passe VARCHAR(255) NOT NULL,
            telephone VARCHAR(20),
            role ENUM('admin', 'syndic', 'resident') NOT NULL,
            syndic_id INT NULL,
            must_change_password BOOLEAN DEFAULT TRUE,
            is_active BOOLEAN DEFAULT TRUE,
            last_login TIMESTAMP NULL,
            date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT NULL,
            INDEX idx_email (email),
            INDEX idx_role (role),
            INDEX idx_syndic_id (syndic_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'subscriptions' => "
        CREATE TABLE IF NOT EXISTS subscriptions (
            id_subscription INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL,
            duration_months INT NOT NULL DEFAULT 12,
            max_residents INT DEFAULT 50,
            max_apartments INT DEFAULT 100,
            features JSON,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'subscription_purchases' => "
        CREATE TABLE IF NOT EXISTS subscription_purchases (
            id INT PRIMARY KEY AUTO_INCREMENT,
            subscription_id INT NOT NULL,
            syndic_email VARCHAR(255) NOT NULL,
            syndic_name VARCHAR(100) NOT NULL,
            syndic_phone VARCHAR(20),
            company_name VARCHAR(100),
            company_address TEXT,
            payment_status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            payment_method VARCHAR(50),
            transaction_id VARCHAR(100),
            amount_paid DECIMAL(10,2),
            purchase_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expiry_date DATE,
            is_processed BOOLEAN DEFAULT FALSE,
            processed_by INT NULL,
            processed_date TIMESTAMP NULL,
            notes TEXT,
            INDEX idx_email (syndic_email),
            INDEX idx_status (payment_status),
            INDEX idx_processed (is_processed),
            INDEX idx_purchase_date (purchase_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'syndic' => "
        CREATE TABLE IF NOT EXISTS syndic (
            id_syndic INT PRIMARY KEY AUTO_INCREMENT,
            nom_syndic VARCHAR(100) NOT NULL,
            code_syndic VARCHAR(20) NOT NULL UNIQUE,
            adresse_syndic VARCHAR(255),
            ville VARCHAR(100),
            code_postal VARCHAR(10),
            telephone VARCHAR(20),
            email VARCHAR(255),
            nombre_etages_batiment_principal INT DEFAULT 1,
            nombre_total_appartements INT DEFAULT 0,
            date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            id_admin_syndic INT NOT NULL,
            subscription_id INT NULL,
            INDEX idx_code_syndic (code_syndic),
            INDEX idx_admin_syndic (id_admin_syndic)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'syndic_subscriptions' => "
        CREATE TABLE IF NOT EXISTS syndic_subscriptions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            syndic_id INT NOT NULL,
            subscription_id INT NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            payment_status ENUM('active','expired','suspended','cancelled') DEFAULT 'active',
            payment_method VARCHAR(50),
            transaction_id VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_syndic_subscription (syndic_id),
            INDEX idx_status (payment_status),
            INDEX idx_dates (start_date, end_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'residents' => "
        CREATE TABLE IF NOT EXISTS residents (
            id_resident INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            syndic_id INT NOT NULL,
            apartment_id INT NULL,
            date_adhesion DATE DEFAULT CURDATE(),
            date_fin_bail DATE,
            type_resident ENUM('proprietaire','locataire','gerant') DEFAULT 'locataire',
            is_active BOOLEAN DEFAULT TRUE,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_syndic (user_id, syndic_id),
            INDEX idx_syndic_resident (syndic_id),
            INDEX idx_apartment (apartment_id),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'appartement' => "
        CREATE TABLE IF NOT EXISTS appartement (
            id_appartement INT PRIMARY KEY AUTO_INCREMENT,
            syndic_id INT NOT NULL,
            numero_appartement VARCHAR(10) NOT NULL,
            etage INT DEFAULT 0,
            surface DECIMAL(8,2),
            nombre_pieces INT,
            nombre_chambres INT,
            type_appartement ENUM('studio','F1','F2','F3','F4','F5+') DEFAULT 'F2',
            balcon BOOLEAN DEFAULT FALSE,
            parking BOOLEAN DEFAULT FALSE,
            cave BOOLEAN DEFAULT FALSE,
            resident_id INT NULL,
            loyer_mensuel DECIMAL(10,2),
            charges_mensuelles DECIMAL(10,2),
            is_occupied BOOLEAN DEFAULT FALSE,
            date_occupation DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_apt_per_syndic (syndic_id, numero_appartement),
            INDEX idx_syndic_apt (syndic_id),
            INDEX idx_resident (resident_id),
            INDEX idx_occupied (is_occupied)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'demandes_maintenance' => "
        CREATE TABLE IF NOT EXISTS demandes_maintenance (
            id_demande INT PRIMARY KEY AUTO_INCREMENT,
            resident_id INT NOT NULL,
            syndic_id INT NOT NULL,
            apartment_id INT NULL,
            titre VARCHAR(200) NOT NULL,
            description TEXT NOT NULL,
            type_probleme ENUM('plomberie','electricite','chauffage','serrurerie','peinture','nettoyage','general','urgence') NOT NULL,
            priorite ENUM('basse','normale','haute','urgente') DEFAULT 'normale',
            statut ENUM('nouvelle','vue','en_cours','terminee','annulee','rejetee') DEFAULT 'nouvelle',
            date_demande TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            date_vue TIMESTAMP NULL,
            date_affectation TIMESTAMP NULL,
            date_resolution TIMESTAMP NULL,
            cout_reparation DECIMAL(10,2),
            cout_estime DECIMAL(10,2),
            notes_syndic TEXT,
            notes_resolution TEXT,
            photos JSON,
            is_urgent BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_resident_demande (resident_id),
            INDEX idx_syndic_demande (syndic_id),
            INDEX idx_statut (statut),
            INDEX idx_priorite (priorite),
            INDEX idx_date_demande (date_demande),
            INDEX idx_apartment (apartment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'affectations_maintenance' => "
        CREATE TABLE IF NOT EXISTS affectations_maintenance (
            id_affectation INT PRIMARY KEY AUTO_INCREMENT,
            demande_id INT NOT NULL,
            assigned_by INT NOT NULL,
            prestataire_nom VARCHAR(100),
            prestataire_telephone VARCHAR(20),
            prestataire_email VARCHAR(255),
            date_affectation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            date_intervention_prevue DATETIME,
            date_intervention_reelle DATETIME,
            notes_technicien TEXT,
            rapport_intervention TEXT,
            statut ENUM('assignee','contacte','planifiee','en_cours','terminee','reportee') DEFAULT 'assignee',
            cout_final DECIMAL(10,2),
            satisfaction_resident TINYINT CHECK (satisfaction_resident >= 1 AND satisfaction_resident <= 5),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_demande_affectation (demande_id),
            INDEX idx_assigned_by (assigned_by),
            INDEX idx_statut_affectation (statut),
            INDEX idx_date_intervention (date_intervention_prevue)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'factures' => "
        CREATE TABLE IF NOT EXISTS factures (
            id_facture INT PRIMARY KEY AUTO_INCREMENT,
            numero_facture VARCHAR(50) NOT NULL UNIQUE,
            syndic_id INT NOT NULL,
            resident_id INT NULL,
            apartment_id INT NULL,
            type_facture ENUM('maintenance','charges','loyer','subscription','penalite','other') NOT NULL,
            montant DECIMAL(10,2) NOT NULL,
            montant_tva DECIMAL(10,2) DEFAULT 0.00,
            montant_total DECIMAL(10,2) NOT NULL,
            description TEXT,
            date_emission DATE NOT NULL,
            date_echeance DATE NOT NULL,
            statut_paiement ENUM('impayee','payee','en_retard','partiellement_payee','annulee') DEFAULT 'impayee',
            date_paiement DATE,
            mode_paiement ENUM('especes','cheque','virement','carte','prelevement'),
            reference_paiement VARCHAR(100),
            demande_id INT NULL,
            fichier_facture VARCHAR(255),
            rappels_envoyes TINYINT DEFAULT 0,
            date_dernier_rappel DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_numero_facture (numero_facture),
            INDEX idx_syndic_facture (syndic_id),
            INDEX idx_resident_facture (resident_id),
            INDEX idx_statut_paiement (statut_paiement),
            INDEX idx_date_emission (date_emission),
            INDEX idx_date_echeance (date_echeance)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'annonces' => "
        CREATE TABLE IF NOT EXISTS annonces (
            id_annonce INT PRIMARY KEY AUTO_INCREMENT,
            syndic_id INT NOT NULL,
            created_by INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            contenu TEXT NOT NULL,
            resume VARCHAR(500),
            date_publication TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            date_expiration DATE,
            visible_pour_residents BOOLEAN DEFAULT TRUE,
            is_urgent BOOLEAN DEFAULT FALSE,
            is_published BOOLEAN DEFAULT TRUE,
            type_annonce ENUM('general','maintenance','reunions','charges','travaux','securite','evenement') DEFAULT 'general',
            piece_jointe VARCHAR(255),
            nombre_vues INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_syndic_annonce (syndic_id),
            INDEX idx_publication (date_publication),
            INDEX idx_type (type_annonce),
            INDEX idx_urgent (is_urgent),
            INDEX idx_published (is_published)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'menus' => "
        CREATE TABLE IF NOT EXISTS menus (
            id_menu INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL UNIQUE,
            icon VARCHAR(50),
            parent_id INT NULL,
            sort_order INT DEFAULT 0,
            is_active BOOLEAN DEFAULT TRUE,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (slug),
            INDEX idx_parent (parent_id),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'role_permissions' => "
        CREATE TABLE IF NOT EXISTS role_permissions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            role ENUM('admin','syndic','resident') NOT NULL,
            menu_id INT NOT NULL,
            can_view BOOLEAN DEFAULT FALSE,
            can_create BOOLEAN DEFAULT FALSE,
            can_edit BOOLEAN DEFAULT FALSE,
            can_delete BOOLEAN DEFAULT FALSE,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_role_menu (role, menu_id),
            INDEX idx_role (role),
            INDEX idx_menu (menu_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'system_settings' => "
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            setting_type ENUM('string','number','boolean','json') DEFAULT 'string',
            description TEXT,
            is_public BOOLEAN DEFAULT FALSE,
            updated_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_key (setting_key),
            INDEX idx_public (is_public)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'activity_logs' => "
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_activity (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        'password_reset_tokens' => "
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            token VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP,
            used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_user (user_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ];

    // Create all tables
    foreach ($tables as $tableName => $sql) {
        $pdo->exec($sql);
        echo "<p>✅ Table '$tableName' created</p>";
    }

    echo "<h2>📋 Step 3: Adding Foreign Key Constraints</h2>";

    // Foreign key constraints
    $constraints = [
        "ALTER TABLE subscription_purchases ADD CONSTRAINT fk_sub_purchase_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id_subscription) ON DELETE CASCADE",
        "ALTER TABLE subscription_purchases ADD CONSTRAINT fk_sub_purchase_processed FOREIGN KEY (processed_by) REFERENCES utilisateur(id_utilisateur) ON DELETE SET NULL",
        "ALTER TABLE syndic ADD CONSTRAINT fk_syndic_admin FOREIGN KEY (id_admin_syndic) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE",
        "ALTER TABLE syndic ADD CONSTRAINT fk_syndic_subscription FOREIGN KEY (subscription_id) REFERENCES syndic_subscriptions(id) ON DELETE SET NULL",
        "ALTER TABLE syndic_subscriptions ADD CONSTRAINT fk_syndic_sub_syndic FOREIGN KEY (syndic_id) REFERENCES syndic(id_syndic) ON DELETE CASCADE",
        "ALTER TABLE syndic_subscriptions ADD CONSTRAINT fk_syndic_sub_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id_subscription) ON DELETE CASCADE",
        "ALTER TABLE residents ADD CONSTRAINT fk_residents_user FOREIGN KEY (user_id) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE",
        "ALTER TABLE residents ADD CONSTRAINT fk_residents_syndic FOREIGN KEY (syndic_id) REFERENCES syndic(id_syndic) ON DELETE CASCADE",
        "ALTER TABLE residents ADD CONSTRAINT fk_residents_apartment FOREIGN KEY (apartment_id) REFERENCES appartement(id_appartement) ON DELETE SET NULL",
        "ALTER TABLE appartement ADD CONSTRAINT fk_appartement_syndic FOREIGN KEY (syndic_id) REFERENCES syndic(id_syndic) ON DELETE CASCADE",
        "ALTER TABLE appartement ADD CONSTRAINT fk_appartement_resident FOREIGN KEY (resident_id) REFERENCES residents(id_resident) ON DELETE SET NULL",
        "ALTER TABLE demandes_maintenance ADD CONSTRAINT fk_demande_resident FOREIGN KEY (resident_id) REFERENCES residents(id_resident) ON DELETE CASCADE",
        "ALTER TABLE demandes_maintenance ADD CONSTRAINT fk_demande_syndic FOREIGN KEY (syndic_id) REFERENCES syndic(id_syndic) ON DELETE CASCADE",
        "ALTER TABLE demandes_maintenance ADD CONSTRAINT fk_demande_apartment FOREIGN KEY (apartment_id) REFERENCES appartement(id_appartement) ON DELETE SET NULL",
        "ALTER TABLE affectations_maintenance ADD CONSTRAINT fk_affectation_demande FOREIGN KEY (demande_id) REFERENCES demandes_maintenance(id_demande) ON DELETE CASCADE",
        "ALTER TABLE affectations_maintenance ADD CONSTRAINT fk_affectation_assigned FOREIGN KEY (assigned_by) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE",
        "ALTER TABLE factures ADD CONSTRAINT fk_facture_syndic FOREIGN KEY (syndic_id) REFERENCES syndic(id_syndic) ON DELETE CASCADE",
        "ALTER TABLE factures ADD CONSTRAINT fk_facture_resident FOREIGN KEY (resident_id) REFERENCES residents(id_resident) ON DELETE SET NULL",
        "ALTER TABLE factures ADD CONSTRAINT fk_facture_apartment FOREIGN KEY (apartment_id) REFERENCES appartement(id_appartement) ON DELETE SET NULL",
        "ALTER TABLE factures ADD CONSTRAINT fk_facture_demande FOREIGN KEY (demande_id) REFERENCES demandes_maintenance(id_demande) ON DELETE SET NULL",
        "ALTER TABLE annonces ADD CONSTRAINT fk_annonce_syndic FOREIGN KEY (syndic_id) REFERENCES syndic(id_syndic) ON DELETE CASCADE",
        "ALTER TABLE annonces ADD CONSTRAINT fk_annonce_created FOREIGN KEY (created_by) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE",
        "ALTER TABLE menus ADD CONSTRAINT fk_menu_parent FOREIGN KEY (parent_id) REFERENCES menus(id_menu) ON DELETE SET NULL",
        "ALTER TABLE role_permissions ADD CONSTRAINT fk_role_menu FOREIGN KEY (menu_id) REFERENCES menus(id_menu) ON DELETE CASCADE",
        "ALTER TABLE role_permissions ADD CONSTRAINT fk_role_created FOREIGN KEY (created_by) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE",
        "ALTER TABLE system_settings ADD CONSTRAINT fk_settings_updated FOREIGN KEY (updated_by) REFERENCES utilisateur(id_utilisateur) ON DELETE SET NULL",
        "ALTER TABLE activity_logs ADD CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE",
        "ALTER TABLE password_reset_tokens ADD CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES utilisateur(id_utilisateur) ON DELETE CASCADE",
        "ALTER TABLE utilisateur ADD CONSTRAINT fk_user_created FOREIGN KEY (created_by) REFERENCES utilisateur(id_utilisateur) ON DELETE SET NULL",
        "ALTER TABLE utilisateur ADD CONSTRAINT fk_user_syndic FOREIGN KEY (syndic_id) REFERENCES utilisateur(id_utilisateur) ON DELETE SET NULL"
    ];

    foreach ($constraints as $constraint) {
        try {
            $pdo->exec($constraint);
            echo "<p>✅ Constraint added successfully</p>";
        } catch (PDOException $e) {
            // Ignore if constraint already exists
            if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                echo "<p>⚠️ Constraint warning: " . $e->getMessage() . "</p>";
            }
        }
    }

    echo "<h2>📋 Step 4: Inserting Default Data</h2>";

    // Check and insert default admin user
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM utilisateur WHERE email = 'admin@syndicate.com'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] == 0) {
        $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO utilisateur (nom_complet, email, mot_de_passe, role, must_change_password, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['System Administrator', 'admin@syndicate.com', $admin_password, 'admin', 0, 1]);
        echo "<p>✅ Default admin user created</p>";
    } else {
        echo "<p>ℹ️ Admin user already exists</p>";
    }

    // Check and insert subscription plans
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM subscriptions");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] == 0) {
        $subscriptions = [
            ['Basic Plan', 'Perfect for small buildings', 29.99, 12, 25, 50, '["basic_maintenance", "resident_management", "basic_reports"]'],
            ['Professional Plan', 'For medium-sized buildings', 59.99, 12, 100, 200, '["advanced_maintenance", "financial_management", "detailed_reports", "email_notifications"]'],
            ['Enterprise Plan', 'For large residential complexes', 99.99, 12, 500, 1000, '["full_features", "priority_support", "advanced_analytics", "custom_reports", "api_access"]']
        ];

        $stmt = $pdo->prepare("INSERT INTO subscriptions (name, description, price, duration_months, max_residents, max_apartments, features) VALUES (?, ?, ?, ?, ?, ?, ?)");

        foreach ($subscriptions as $sub) {
            $stmt->execute($sub);
        }
        echo "<p>✅ Default subscription plans created</p>";
    } else {
        echo "<p>ℹ️ Subscription plans already exist</p>";
    }

    // Check and insert menu items
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM menus");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] == 0) {
        $menus = [
            ['Dashboard', 'dashboard', 'fas fa-tachometer-alt', null, 1, 'Main dashboard'],
            ['Users Management', 'users', 'fas fa-users', null, 2, 'Manage system users'],
            ['Syndic Management', 'syndics', 'fas fa-building', null, 3, 'Manage syndics'],
            ['Residents', 'residents', 'fas fa-user-friends', null, 4, 'Manage residents'],
            ['Apartments', 'apartments', 'fas fa-home', null, 5, 'Manage apartments'],
            ['Maintenance', 'maintenance', 'fas fa-tools', null, 6, 'Maintenance requests'],
            ['Invoices', 'invoices', 'fas fa-file-invoice-dollar', null, 7, 'Financial management'],
            ['Announcements', 'announcements', 'fas fa-bullhorn', null, 8, 'Building announcements'],
            ['Reports', 'reports', 'fas fa-chart-bar', null, 9, 'System reports'],
            ['Settings', 'settings', 'fas fa-cog', null, 10, 'System settings']
        ];

        $stmt = $pdo->prepare("INSERT INTO menus (name, slug, icon, parent_id, sort_order, description) VALUES (?, ?, ?, ?, ?, ?)");

        foreach ($menus as $menu) {
            $stmt->execute($menu);
        }
        echo "<p>✅ Default menu items created</p>";
    } else {
        echo "<p>ℹ️ Menu items already exist</p>";
    }

    // Check and insert system settings
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM system_settings");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] == 0) {
        $settings = [
            ['app_name', 'Syndicate Management Platform', 'string', 'Application name', 1],
            ['app_version', '1.0.0', 'string', 'Application version', 1],
            ['maintenance_auto_assign', 'false', 'boolean', 'Auto-assign maintenance requests', 0],
            ['invoice_due_days', '30', 'number', 'Default invoice due days', 0],
            ['max_file_upload_size', '10485760', 'number', 'Max file upload size in bytes (10MB)', 0],
            ['allowed_file_types', '["jpg","jpeg","png","pdf","doc","docx"]', 'json', 'Allowed file upload types', 0],
            ['email_notifications', 'true', 'boolean', 'Enable email notifications', 0],
            ['maintenance_reminder_days', '7', 'number', 'Days before maintenance reminder', 0]
        ];

        $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES (?, ?, ?, ?, ?)");

        foreach ($settings as $setting) {
            $stmt->execute($setting);
        }
        echo "<p>✅ Default system settings created</p>";
    } else {
        echo "<p>ℹ️ System settings already exist</p>";
    }

    echo "<h2>🎉 Setup Complete!</h2>";
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>✅ Database Setup Summary:</h3>";
    echo "<ul>";
    echo "<li>✅ Database 'syndicate_platform' created</li>";
    echo "<li>✅ 16 tables created with proper indexes</li>";
    echo "<li>✅ Foreign key constraints established</li>";
    echo "<li>✅ Default admin user created</li>";
    echo "<li>✅ 3 subscription plans loaded</li>";

    echo "<li>✅ 10 menu items configured</li>";
    echo "<li>✅ 8 system settings initialized</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div style='background: #cce5ff; border: 1px solid #b3d7ff; color: #004085; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>🔐 Login Credentials:</h3>";
    echo "<p><strong>Admin Access:</strong></p>";
    echo "<p>Email: <code>admin@syndicate.com</code></p>";
    echo "<p>Password: <code>admin123</code></p>";
    echo "</div>";

    echo "<div style='margin: 30px 0; text-align: center;'>";
    echo "<a href='../public/login.php' style='background: #007bff; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>🚀 Go to Login Page</a>";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>❌ Database Setup Error:</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
    echo "</div>";

    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>🔧 Troubleshooting Tips:</h3>";
    echo "<ul>";
    echo "<li>Make sure MySQL server is running</li>";
    echo "<li>Check database connection credentials</li>";
    echo "<li>Ensure MySQL user has CREATE DATABASE privileges</li>";
    echo "<li>Verify PHP PDO MySQL extension is installed</li>";
    echo "</ul>";
    echo "</div>";
}

echo "<hr>";
echo "<div style='text-align: center; color: #6c757d; margin: 30px 0;'>";
echo "<p>© 2025 Syndicate Management Platform - Setup Script v1.0</p>";
echo "<p>Generated on: " . date('Y-m-d H:i:s') . "</p>";
echo "</div>";
?>

<style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        margin: 0;
        padding: 20px;
        min-height: 100vh;
    }

    .container {
        max-width: 800px;
        margin: 0 auto;
        background: white;
        padding: 30px;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    h1 {
        color: #2c3e50;
        text-align: center;
        margin-bottom: 10px;
    }

    h2 {
        color: #34495e;
        border-bottom: 2px solid #3498db;
        padding-bottom: 5px;
    }

    h3 {
        margin-top: 0;
    }

    p {
        margin: 8px 0;
        line-height: 1.5;
    }

    code {
        background: #f8f9fa;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
        border: 1px solid #e9ecef;
    }

    ul {
        margin: 10px 0;
        padding-left: 20px;
    }

    li {
        margin: 5px 0;
    }

    a {
        display: inline-block;
        transition: all 0.3s ease;
    }

    a:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0, 123, 255, 0.3);
    }

    hr {
        border: none;
        height: 2px;
        background: linear-gradient(to right, #3498db, #2ecc71);
        margin: 30px 0;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Add smooth scroll animation for any internal links
        const links = document.querySelectorAll('a[href^="#"]');
        links.forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add loading animation for setup process
        const setupMessages = document.querySelectorAll('p');
        let delay = 0;

        setupMessages.forEach(message => {
            if (message.textContent.includes('✅') || message.textContent.includes('ℹ️')) {
                setTimeout(() => {
                    message.style.opacity = '0';
                    message.style.transform = 'translateX(-20px)';
                    message.style.transition = 'all 0.5s ease';

                    setTimeout(() => {
                        message.style.opacity = '1';
                        message.style.transform = 'translateX(0)';
                    }, 100);
                }, delay);
                delay += 200;
            }
        });

        // Auto-redirect to login after successful setup (optional)
        const successElement = document.querySelector('h2:contains("🎉")');
        if (successElement && successElement.textContent.includes('Setup Complete')) {
            setTimeout(() => {
                const redirect = confirm('Setup completed successfully! Would you like to go to the login page now?');
                if (redirect) {
                    window.location.href = '../public/login.php';
                }
            }, 5000);
        }
    });

    // Function to copy login credentials to clipboard
    function copyCredentials() {
        const credentials = "Email: admin@syndicate.com\nPassword: admin123";
        navigator.clipboard.writeText(credentials).then(() => {
            alert('Login credentials copied to clipboard!');
        }).catch(err => {
            console.error('Could not copy text: ', err);
        });
    }
</script>