<?php

namespace App\Services\Wifi;

use App\Models\WifiNetwork;
use App\Services\SettingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Kartu WiFi A6 siap cetak: QR code (WIFI:T:…;S:…;P:…;;), SSID, dan password. Khusus petugas.
 */
class WifiQrCard
{
    public function __construct(private readonly SettingsService $settings) {}

    public function pdf(WifiNetwork $network): string
    {
        return Pdf::loadView('pdf.wifi-card', [
            'network' => $network,
            'qr' => $this->qrDataUri($network),
            'institution' => $this->settings->get('identity.institution_name'),
            'unit' => $this->settings->get('identity.unit_name'),
        ])
            ->setPaper('a6')
            ->setOption('isFontSubsettingEnabled', true)
            ->output();
    }

    public function filename(WifiNetwork $network): string
    {
        return 'kartu-wifi-'.str($network->ssid)->slug().'.pdf';
    }

    public function qrDataUri(WifiNetwork $network): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'outputBase64' => true,
            'eccLevel' => EccLevel::M,
            'scale' => 10,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
        ]);

        return (new QRCode($options))->render($network->qrPayload());
    }
}
