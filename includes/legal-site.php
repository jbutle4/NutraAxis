<?php

const LEGAL_APP_NAME = 'NutraAxis Operations';
const LEGAL_COMPANY_NAME = 'NutraAxis LLC';
const LEGAL_EFFECTIVE_DATE = 'June 6, 2026';
const LEGAL_CONTACT_EMAIL = 'info@nutraaxis.com';
const LEGAL_GOVERNING_LAW = 'the State of Florida, United States';

function legal_site_url(): string
{
    $siteUrl = rtrim(trim((string) env('SITE_URL', '')), '/');

    return $siteUrl !== '' ? $siteUrl : 'https://nutraaxisweb.azurewebsites.net';
}

function legal_eula_url(): string
{
    return legal_site_url() . '/eula/';
}

function legal_privacy_url(): string
{
    return legal_site_url() . '/privacy-policy/';
}
