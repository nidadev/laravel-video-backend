<?php
namespace App\Services;

use Aws\MediaConvert\MediaConvertClient;

class MediaConvertService
{
    protected $client;

    public function __construct()
    {
        $this->client = new MediaConvertClient([
            'version' => 'latest',
            'region'  => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
            'endpoint' => env('AWS_MEDIACONVERT_ENDPOINT'),
        ]);
    }

    public function createHlsJob($inputS3Url, $outputS3Folder, $nameModifier)
    {
        $job = $this->client->createJob([
            'Role' => env('AWS_MEDIACONVERT_ROLE'),
            'Settings' => [
                'Inputs' => [
                    [
                        'FileInput' => $inputS3Url,
                        'AudioSelectors' => [
                            'Audio Selector 1' => ['DefaultSelection' => 'DEFAULT']
                        ],
                        'VideoSelector' => [],
                        'TimecodeSource' => 'ZEROBASED'
                    ]
                ],
                'OutputGroups' => [
                    [
                        'Name' => 'Apple HLS',
                        'OutputGroupSettings' => [
                            'Type' => 'HLS_GROUP_SETTINGS',
                            'HlsGroupSettings' => [
                                'SegmentLength' => 10,
                                'Destination' => $outputS3Folder,
                                'MinSegmentLength' => 0,
                                'ManifestCompression' => 'NONE'
                            ]
                        ],
                        'Outputs' => [
                            [
                                'ContainerSettings' => [
                                    'Container' => 'M3U8',
                                    'M3u8Settings' => []
                                ],
                                'VideoDescription' => [
                                    'CodecSettings' => [
                                        'Codec' => 'H_264',
                                        'H264Settings' => [
                                            'MaxBitrate' => 3000000,
                                            'RateControlMode' => 'QVBR',
                                            'SceneChangeDetect' => 'TRANSITION_DETECTION'
                                        ]
                                    ]
                                ],
                                'AudioDescriptions' => [
                                    [
                                        'CodecSettings' => [
                                            'Codec' => 'AAC',
                                            'AacSettings' => [
                                                'Bitrate' => 96000,
                                                'CodingMode' => 'CODING_MODE_2_0',
                                                'SampleRate' => 48000
                                            ]
                                        ]
                                    ]
                                ],
                                'NameModifier' => $nameModifier // original filename with spaces
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        return $job['Job']['Id'];
    }
}
