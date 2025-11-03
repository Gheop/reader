<?php
ini_set('max_execution_time', '500');

include('/www/conf.php');


$logFile = __DIR__ . '/log_article.txt';

$article='';

// Vérifie si le champ 'article' existe dans $_POST
if (isset($_POST['article'])) {
    $article = $_POST['article'];
} else {
    echo "Aucune donnée reçue.";
    return;
}
// URL de l'API OpenAI (ou DeepSeek)
$apiUrl = 'https://api.deepseek.com/chat/completions'; // Remplacez par l'URL de DeepSeek si disponible

// Données à envoyer à l'API
$data = [
    'model' => 'deepseek-chat', // Modèle à utiliser (remplacez par le modèle DeepSeek si disponible)
    'messages' => [
        [
            'role' => 'system',
            'content' => 'Vous êtes un assistant utile et adoré. Faites un résumé concis de cet article de presse en français.'
        ],
        [
            'role' => 'user',
            'content' => $article
        ]
    ],
    'temperature' => 0.8,
    'max_tokens' => 750 // Limite la longueur du résumé
];

// Initialisation de cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $AI_apiKey
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Exécution de la requête
$response = curl_exec($ch);
curl_close($ch);

// Décodage de la réponse JSON
$responseData = json_decode($response, true);

// Affichage du résumé
if (isset($responseData['choices'][0]['message']['content'])) {
    $summary = $responseData['choices'][0]['message']['content'];
    echo "<u>Résumé de l'article :</u><br>";
    echo nl2br($summary);
} else {
        
    // Ouvre le fichier en mode "append" (ajout en fin de fichier)
    // Si le fichier n'existe pas, il sera créé
    $handle = fopen($logFile, 'a');

    if ($handle) {
        // Écrit la date/heure pour savoir quand le log a été fait
        fwrite($handle, "=== Nouvelle entrée : " . date('Y-m-d H:i:s') . " ===\n");
        // Écrit le contenu de l’article
        fwrite($handle, $article . "\n\n");
        // Ferme le fichier
        fclose($handle);

     //   echo "Données reçues et loguées avec succès.";
    } else {
        echo "Impossible d'ouvrir le fichier de log.";
    }
    echo "Erreur lors de la génération du résumé : " . json_encode($responseData);
}
