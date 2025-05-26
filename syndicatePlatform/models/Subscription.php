<?php
class Subscription {
    private $conn;
    private $table = "subscriptions";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllActive() {
        $query = "SELECT * FROM " . $this->table . " WHERE is_active = 1 ORDER BY price ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id_subscription = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
       return $stmt->fetch(PDO::FETCH_ASSOC);
   }

   public function create($data) {
       $query = "INSERT INTO " . $this->table . "
                 (name, description, price, duration_months, max_residents, max_apartments, features)
                 VALUES (:name, :description, :price, :duration_months, :max_residents, :max_apartments, :features)";

       $stmt = $this->conn->prepare($query);
       
       $stmt->bindParam(':name', $data['name']);
       $stmt->bindParam(':description', $data['description']);
       $stmt->bindParam(':price', $data['price']);
       $stmt->bindParam(':duration_months', $data['duration_months']);
       $stmt->bindParam(':max_residents', $data['max_residents']);
       $stmt->bindParam(':max_apartments', $data['max_apartments']);
       $stmt->bindParam(':features', json_encode($data['features']));

       return $stmt->execute();
   }

   public function update($id, $data) {
       $query = "UPDATE " . $this->table . " 
                 SET name = :name, description = :description, price = :price, 
                     duration_months = :duration_months, max_residents = :max_residents, 
                     max_apartments = :max_apartments, features = :features
                 WHERE id_subscription = :id";

       $stmt = $this->conn->prepare($query);
       
       $stmt->bindParam(':id', $id);
       $stmt->bindParam(':name', $data['name']);
       $stmt->bindParam(':description', $data['description']);
       $stmt->bindParam(':price', $data['price']);
       $stmt->bindParam(':duration_months', $data['duration_months']);
       $stmt->bindParam(':max_residents', $data['max_residents']);
       $stmt->bindParam(':max_apartments', $data['max_apartments']);
       $stmt->bindParam(':features', json_encode($data['features']));

       return $stmt->execute();
   }

   public function delete($id) {
       $query = "UPDATE " . $this->table . " SET is_active = 0 WHERE id_subscription = :id";
       $stmt = $this->conn->prepare($query);
       $stmt->bindParam(':id', $id);
       return $stmt->execute();
   }
}
?>