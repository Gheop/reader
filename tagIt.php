<?php
ini_set('max_execution_time', '500');
include(__DIR__ . '/config/conf.php');

$text = '';
if(isset($_POST['text'])) $text = $_POST['text'];
if(isset($_GET['text'])) $text = $_GET['text'];
if(empty($text)) {
    echo 'No text.';
    die();
}
/*else {
    print_r($text);
}*/
// Données à envoyer à l'API
$data = [
    'model' => $AI_model, // Modèle à utiliser (remplacez par le modèle DeepSeek si disponible)
    'messages' => [
        [
            'role' => 'system',
            'content' => 'Analyse l\'article suivant et extrait entre 1 et 5 tags les plus importants qui le qualifient et le résume. Réponds uniquement au format texte avec la structure suivante : tag1, tag2, tag3, ...

            '
        ],
        [
            'role' => 'user',
            'content' => $text
        ]
    ],
    'temperature' => 1.3,
    'max_tokens' => 1000 // Limite la longueur du résumé
];

//echo $AI_apiKey.'|'.$AI_apiUrl.'|'.$AI_model;
// Initialisation de cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $AI_apiUrl);
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
    echo $responseData['choices'][0]['message']['content'];
} else {
    echo json_encode($responseData);
}
?>