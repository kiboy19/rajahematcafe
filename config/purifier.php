<?php

return [

    'encoding' => 'UTF-8',
    'finalize' => true,
    'ignoreNonStrings' => false,

    'cachePath' => storage_path('app/purifier'),
    'cacheFileMode' => 0755,

    'settings' => [

        'default' => [
            'HTML.Doctype' => 'HTML 4.01 Transitional',
            'HTML.Allowed' => 'p,h2,h3,h4,ul,ol,li,blockquote,strong,em,u,s,a[href|rel|target],img[src|alt],br,hr,code,pre',
            'CSS.AllowedProperties' => [],
            'AutoFormat.AutoParagraph' => false,
            'AutoFormat.RemoveEmpty' => true,
            'AutoFormat.RemoveEmpty.RemoveNbsp' => true,
            'Attr.AllowedRel' => 'noopener,nofollow,noreferrer',
            'Attr.AllowedFrameTargets' => ['_blank'],
            'URI.AllowedSchemes' => [
                'http' => true,
                'https' => true,
                'mailto' => true,
            ],
            'URI.SafeIframeRegexp' => '%^https://(www\.)?(youtube\.com/embed/|player\.vimeo\.com/video/)%',
        ],

    ],

];
