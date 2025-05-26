<?php
class User {
    private $conn;
    private $table = "utilisateur";

    public $id_utilisateur;
    public $nom_complet;
    public $email;
    public $mot_de_passe;
    public $telephone;
    public $role;
    public $syndic_id;
    public $must_change_password;
    public $is_active;
    public $created_by;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function authenticate($email, $password) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE email = :email AND is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(password_verify($password, $row['mot_de_passe'])) {
                $this->id_utilisateur = $row['id_utilisateur'];
                $this->nom_complet = $row['nom_complet'];
                $this->email = $row['email'];
                $this->role = $row['role'];
                $this->syndic_id = $row['syndic_id'];
                $this->must_change_password = $row['must_change_password'];
                
                // Update last login
                $this->updateLastLogin();
                return true;
            }
        }
        return false;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . "
                  (nom_complet, email, mot_de_passe, telephone, role, syndic_id, created_by)
                  VALUES (:nom_complet, :email, :mot_de_passe, :telephone, :role, :syndic_id, :created_by)";

        $stmt = $this->conn->prepare($query);
        
        $this->mot_de_passe = password_hash($this->mot_de_passe, HASH_ALGO);
        
        $stmt->bindParam(':nom_complet', $this->nom_complet);
        $stmt->bindParam(':email', $this->email);
        $stmt->bindParam(':mot_de_passe', $this->mot_de_passe);
        $stmt->bindParam(':telephone', $this->telephone);
        $stmt->bindParam(':role', $this->role);
        $stmt->bindParam(':syndic_id', $this->syndic_id);
        $stmt->bindParam(':created_by', $this->created_by);

        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    //syndic change pass
    public function changePassword($old_password, $new_password) {
        if(password_verify($old_password, $this->getCurrentPassword())) {
            $query = "UPDATE " . $this->table . " 
                      SET mot_de_passe = :new_password, must_change_password = 0 
                      WHERE id_utilisateur = :id";
            
            $stmt = $this->conn->prepare($query);
            $hashed_password = password_hash($new_password, HASH_ALGO);
            
            $stmt->bindParam(':new_password', $hashed_password);
            $stmt->bindParam(':id', $this->id_utilisateur);
            
            return $stmt->execute();
        }
        return false;
    }

    public function getBySyndic($syndic_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE syndic_id = :syndic_id AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':syndic_id', $syndic_id);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id_utilisateur = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function getCurrentPassword() {
        $query = "SELECT mot_de_passe FROM " . $this->table . " WHERE id_utilisateur = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id_utilisateur);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['mot_de_passe'];
    }

    private function updateLastLogin() {
        $query = "UPDATE " . $this->table . " SET last_login = NOW() WHERE id_utilisateur = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id_utilisateur);
        $stmt->execute();
    }
}
?>