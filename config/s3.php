<?php
// s3.php — MinIO S3 Client Configuration
// Requires: composer require aws/aws-sdk-php

require __DIR__ . '/../vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// MinIO Configuration
$S3_ENDPOINT = 'http://localhost:9000';
$S3_ACCESS_KEY = 'admin';
$S3_SECRET_KEY = 'password123';
$S3_BUCKET = 'ebgr';
$S3_REGION = 'us-east-1'; // MinIO requires a region even if unused

// Create S3 Client
$s3 = new S3Client([
    'version'     => 'latest',
    'region'      => $S3_REGION,
    'endpoint'    => $S3_ENDPOINT,
    'use_path_style_endpoint' => true, // Required for MinIO
    'credentials' => [
        'key'    => $S3_ACCESS_KEY,
        'secret' => $S3_SECRET_KEY,
    ],
]);

// Ensure bucket exists on first load
try {
    if (!$s3->doesBucketExist($S3_BUCKET)) {
        $s3->createBucket(['Bucket' => $S3_BUCKET]);
        // Set bucket policy to public-read so images can be displayed in <img> tags
        $policy = json_encode([
            'Version' => '2012-10-17',
            'Statement' => [[
                'Sid' => 'PublicRead',
                'Effect' => 'Allow',
                'Principal' => '*',
                'Action' => 's3:GetObject',
                'Resource' => "arn:aws:s3:::$S3_BUCKET/*"
            ]]
        ]);
        $s3->putBucketPolicy([
            'Bucket' => $S3_BUCKET,
            'Policy' => $policy
        ]);
    }
} catch (AwsException $e) {
    error_log("S3 bucket init error: " . $e->getMessage());
}
?>
