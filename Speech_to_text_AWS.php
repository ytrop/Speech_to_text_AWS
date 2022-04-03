
<?php
set_time_limit(0);
 
require 'vendor/autoload.php';
  
use Aws\S3\S3Client;
use Aws\TranscribeService\TranscribeServiceClient;
 
if ( isset($_POST['submit']) ) {
    // Check if media file is supported
    $arr_mime_types = ['mp3', 'mp4', 'wav', 'flac', 'ogg', 'amr', 'webm'];
    $media_format = pathinfo($_FILES['media']['name'])['extension'];
    if ( !in_array($media_format, $arr_mime_types) ) {
        die('File type is not allowed');
    }
  
    // pass AWS API credentials
    $region = 'PASS_REGION';
    $access_key = 'ACCESS_KEY';
    $secret_access_key = 'SECRET_ACCESS_KEY';
     
    // Specify S3 bucket name
    $bucketName = 'PASS_BUCKET_NAME'
  
    // Instantiate an Amazon S3 client.
    $s3 = new S3Client([
        'version' => 'latest',
        'region'  => $region,
        'credentials' => [
            'key'    => $access_key,
            'secret' => $secret_access_key
        ]
    ]);
  
    $key = basename($_FILES['media']['name']);
  
    // upload file on S3 Bucket
    try {
        $result = $s3->putObject([
            'Bucket' => $bucketName,
            'Key'    => $key,
            'Body'   => fopen($_FILES['media']['tmp_name'], 'r'),
            'ACL'    => 'public-read',
        ]);
        $audio_url = $result->get('ObjectURL');
  
        // Code for Amazon Transcribe Service - Start here
        // Create Amazon Transcribe Client
        $awsTranscribeClient = new TranscribeServiceClient([
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key'    => $access_key,
                'secret' => $secret_access_key
            ]
        ]);
         
        // Start a Transcription Job
        $job_id = uniqid();
        $transcriptionResult = $awsTranscribeClient->startTranscriptionJob([
                'LanguageCode' => 'en-US',
                'Media' => [
                    'MediaFileUri' => $audio_url,
                ],
                'TranscriptionJobName' => $job_id,
        ]);
         
        $status = array();
        while(true) {
            $status = $awsTranscribeClient->getTranscriptionJob([
                'TranscriptionJobName' => $job_id
            ]);
         
            if ($status->get('TranscriptionJob')['TranscriptionJobStatus'] == 'COMPLETED') {
                break;
            }
         
            sleep(5);
        }
         
        // download the converted txt file
        $url = $status->get('TranscriptionJob')['Transcript']['TranscriptFileUri'];
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        $data = curl_exec($curl);
        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
            echo $error_msg;
        }
        curl_close($curl);
        $arr_data = json_decode($data);
         
        // Send converted txt file to a browser
        $file = $job_id.".txt";
        $txt = fopen($file, "w") or die("Unable to open file!");
        fwrite($txt, $arr_data->results->transcripts[0]->transcript);
        fclose($txt);
         
        header('Content-Description: File Transfer');
        header('Content-Disposition: attachment; filename='.basename($file));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        header("Content-Type: text/plain");
        readfile($file);
        exit();
    }  catch (Exception $e) {
        echo $e->getMessage();
    }
}
?>
 
<form method="post" enctype="multipart/form-data">
    <p><input type="file" name="media" accept="audio/*,video/*" /></p>
    <input type="submit" name="submit" value="Submit" />
</form>