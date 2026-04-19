<?php
$googleVisionApiKey = 'AIzaSyAcnhL48wJ1r_iSiLX_uUfXmZ5o7dmhr38';
$imagePath = '/Applications/XAMPP/xamppfiles/htdocs/iot/uploads/ktp/temp/mobile_5HCLFB_1773028033.jpeg';

$imageData = base64_encode(file_get_contents($imagePath));
$payload = [
    "requests" => [
        [
            "image" => [
                "content" => $imageData
            ],
            "features" => [
                [
                    // "type" => "TEXT_DETECTION" // Testing TEXT_DETECTION too
                    "type" => "TEXT_DETECTION"
                ]
            ],
            "imageContext" => [
                "languageHints" => ["id", "en"]
            ]
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://vision.googleapis.com/v1/images:annotate?key=" . $googleVisionApiKey);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);
if (isset($result['responses'][0]['textAnnotations'])) {
    $texts = $result['responses'][0]['textAnnotations'];
    echo "Total Annotations: " . count($texts) . "\n";
    foreach ($texts as $i => $annotation) {
        $desc = str_replace("\n", " ", $annotation['description']);
        if (preg_match('/3\s*2\s*0\s*4\s*0\s*8/i', $desc) || preg_match('/3204/i', $desc) || preg_match('/003/i', $desc)) {
            echo "MATCH in Annotation [$i]: " . substr($desc, 0, 100) . "\n";
        }
    }
} else {
    echo "No textAnnotations found.\n";
}
file_put_contents('/Applications/XAMPP/xamppfiles/htdocs/iot/vision_debug_test.json', $response);
echo "Raw JSON saved to vision_debug_test.json\n";
