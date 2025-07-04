<?php
$page_title = "Purchase Successful - Syndic Way";

require __DIR__ . "/../config.php";

$member_id = $_GET['id'] ?? null;

if (!$member_id) {
    header('Location: index.php');
    exit();
}

// Simplified and corrected query
$query = "
    SELECT 
        m.full_name, 
        s.name_subscription AS plan_name,
        ams.amount AS amount_paid,
        ams.date_payment AS purchase_date,
        r.name AS company_name,
        c.city_name AS company_city
    FROM member m
    JOIN admin_member_subscription ams ON ams.id_member = m.id_member
    JOIN subscription s ON s.id_subscription = ams.id_subscription
    JOIN apartment ap ON ap.id_member = m.id_member
    JOIN residence r ON r.id_residence = ap.id_residence
    JOIN city c ON c.id_city = r.id_city
    WHERE m.id_member = :id
    ORDER BY ams.date_payment DESC
    LIMIT 1
";

$stmt = $conn->prepare($query);
$stmt->bindParam(':id', $member_id, PDO::PARAM_INT);
$stmt->execute();
$purchase = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$purchase) {
    header('Location: index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/sections/purchase-success.css">
    <link rel="stylesheet" href="http://localhost/syndicplatform/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <section class="success-page">
        <div class="container">
            <div class="success-content">
                <div class="success-icon">
                    <i class="fas fa-check-circle"></i>
                </div>

                <h1>Achat réussi !</h1>
                <p class="success-message">Merci d'avoir choisi Syndic Way. Votre abonnement a été activé avec succès.
                </p>


                <div class="purchase-details">
                    <h3>Détails de l'achat</h3>
                    <div class="detail-row">
                        <span>Forfait :</span>
                        <span><?php echo htmlspecialchars($purchase['plan_name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Ville :</span>
                        <span><?php echo htmlspecialchars($purchase['company_city'] ?? 'Non spécifiée'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Residence :</span>
                        <span><?php echo htmlspecialchars($purchase['company_name'] ?? 'Non spécifiée'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Montant :</span>
                        <span><?php echo number_format($purchase['amount_paid'], 2); ?>DH</span>
                    </div>
                    <div class="detail-row">
                        <span>Date d'achat :</span>
                        <span><?php echo date('j F Y', strtotime($purchase['purchase_date'])); ?></span>
                    </div>
                </div>

                <div class="next-steps">
                    <h3>Et maintenant ?</h3>
                    <div class="steps">
                        <div class="step">
                            <div class="step-number">1</div>
                            <div class="step-content">
                                <h4>Connexion immédiate</h4>
                                <p>Vos identifiants sont prêts ! Connectez-vous dès maintenant avec l'email et le mot de
                                    passe fournis.</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">2</div>
                            <div class="step-content">
                                <h4>Configuration du profil</h4>
                                <p>Personnalisez votre profil et ajoutez les informations de votre immeuble.</p>
                            </div>
                        </div>
                        <div class="step">
                            <div class="step-number">3</div>
                            <div class="step-content">
                                <h4>Commencez la gestion</h4>
                                <p>Invitez vos résidents et commencez à gérer votre immeuble efficacement !</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="index.php" class="btn btn-secondary">Retour à l'accueil</a>
                    <a href="mailto:support@syndicate.com" class="btn btn-secondary">Contacter le support</a>
                </div>
            </div>
        </div>
    </section>
</body>

</html>