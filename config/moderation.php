<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Text screening
    |--------------------------------------------------------------------------
    |
    | A coarse, free first pass over vendor-supplied text (truck name,
    | description, menu item names/descriptions). A whole-word, case-insensitive
    | match against this blocklist flags the truck for admin review on save. This
    | is intentionally simple — a stopgap that always runs, even locally. Swap in
    | AWS Comprehend (toxicity) later for nuance. Keep entries lowercase.
    |
    */

    'text_blocklist' => array_values(array_filter(array_map(
        'trim',
        // A tunable default set of slurs/graphic terms; extend via env for a
        // deployment without editing code. Deliberately conservative — the admin
        // queue is the real backstop, this just surfaces the obvious cases.
        explode(',', mb_strtolower((string) env(
            'MODERATION_TEXT_BLOCKLIST',
            '2g1c,4chan,a2m,anal,anilingus,anus,bangbros,bbw,bdsm,'.
            'bestiality,bitch,blowjob,boner,boob,bukkake,butt,chink,clit,'.
            'cocaine,cock,creampie,cuckold,cum,cunnilingus,'.
            'cunt,daterape,deepthroat,dick,dildo,dyke,ejaculation,'.
            'erection,faggot,fecal,fellatio,fingering,fisting,fornicate,'.
            'fuck,g-spot,g-string,genitals,hentai,hermaphrodite,heroin,'.
            'homoerotic,homosexual,jailbait,jizz,lsd,marijuana,masturbate,'.
            'mdma,merkin,milf,naked,nazi,necrophilia,negro,nigga,nigger,'.
            'nude,nympho,orgasm,paedo,paedophile,panties,pedo,pegging,'.
            'penis,piss,porn,pornographic,prostitute,pubes,pussy,queef,rape,'.
            'raping,rectum,retard,rimjob,rimming,scat,schlong,semen,sex,'.
            'shemale,shit,skank,slut,sodomize,sodomy,sperm,spic,spooge,'.
            'strapon,swastika,tans,testicle,thong,tits,titties,tranny,turd,'.
            'twat,twink,upskirt,vagina,vagisil,vibrator,vulva,wank,webcam,'.
            'wetback,whore,whore,whore,wtf,xxx,yaoi'
        ))),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Image screening (AWS Rekognition)
    |--------------------------------------------------------------------------
    |
    | When enabled, each uploaded image is run through Rekognition's
    | DetectModerationLabels; any label at or above the confidence threshold
    | flags the image (and holds its truck for review). Disabled by default so
    | local/CI never reaches out to AWS — App\Actions\ScreenImage treats a
    | disabled screener as "passed" (fail-open). Enable it in production, where
    | the app already has an AWS credential chain (S3, SES).
    |
    */

    'rekognition' => [
        'enabled' => (bool) env('MODERATION_REKOGNITION_ENABLED', true),
        'min_confidence' => (float) env('MODERATION_REKOGNITION_MIN_CONFIDENCE', 80),
        'region' => env('MODERATION_REKOGNITION_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filename image screen (local/CI stand-in for Rekognition)
    |--------------------------------------------------------------------------
    |
    | Rekognition can't run in Lando, so image screening always "passes" locally.
    | To exercise the image flag → hold flow without AWS, set a comma-separated
    | list of trigger words here; when Rekognition is *disabled*, an upload whose
    | original filename contains one of them is flagged (e.g. upload "nsfw.jpg").
    | Empty (the default) = off, so it never surprises a real environment, and it
    | is ignored entirely when Rekognition is enabled (production).
    |
    */

    'image' => [
        'filename_triggers' => array_values(array_filter(array_map(
            'trim',
            explode(',', mb_strtolower((string) env('MODERATION_IMAGE_FILENAME_TRIGGERS', ''))),
        ))),
    ],

];
