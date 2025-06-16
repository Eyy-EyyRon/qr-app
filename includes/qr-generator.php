<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once 'config/database.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;

class QRGenerator {
    public static function generateProductQR($product, $owner_name) {
        try {
            // Create QR data with product information
            $qr_data = json_encode([
                'type' => 'product',
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'price' => '₱' . number_format($product['price'], 2),
                'owner' => $owner_name,
                'description' => $product['description'] ?? '',
                'generated_at' => date('Y-m-d H:i:s'),
                'scan_url' => 'http://' . $_SERVER['HTTP_HOST'] . '/scan.php?id=' . $product['id']
            ]);

            // Create QR codes directory if it doesn't exist
            $qr_dir = 'assets/qr_codes/';
            if (!file_exists($qr_dir)) {
                mkdir($qr_dir, 0777, true);
            }

            $filename = 'qr_' . $product['id'] . '_' . time() . '.png';
            $filepath = $qr_dir . $filename;

            // Generate QR code using endroid/qr-code
            $result = Builder::create()
                ->writer(new PngWriter())
                ->data($qr_data)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
                ->size(300)
                ->margin(10)
                ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->build();

            // Save the QR code
            $result->saveToFile($filepath);
            
            return $filepath;
        } catch (Exception $e) {
            error_log("QR Code generation failed: " . $e->getMessage());
            return false;
        }
    }

    public static function generateCustomQR($data, $filename = null) {
        try {
            $qr_dir = 'assets/qr_codes/';
            if (!file_exists($qr_dir)) {
                mkdir($qr_dir, 0777, true);
            }

            if (!$filename) {
                $filename = 'qr_custom_' . time() . '.png';
            }
            $filepath = $qr_dir . $filename;

            $result = Builder::create()
                ->writer(new PngWriter())
                ->data($data)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
                ->size(300)
                ->margin(10)
                ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->build();

            $result->saveToFile($filepath);
            
            return $filepath;
        } catch (Exception $e) {
            error_log("Custom QR Code generation failed: " . $e->getMessage());
            return false;
        }
    }

    public static function trackScan($product_id, $user_id = null, $location = null) {
        $database = new Database();
        $db = $database->getConnection();

        $query = "INSERT INTO qr_scans (product_id, user_id, ip_address, user_agent, location) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $product_id,
            $user_id,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $location
        ]);
    }

    public static function getScanStats($user_id = null) {
        $database = new Database();
        $db = $database->getConnection();

        if ($user_id) {
            $query = "SELECT COUNT(*) as total_scans FROM qr_scans qs 
                      JOIN products p ON qs.product_id = p.id 
                      WHERE p.user_id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$user_id]);
        } else {
            $query = "SELECT COUNT(*) as total_scans FROM qr_scans";
            $stmt = $db->prepare($query);
            $stmt->execute();
        }

        return $stmt->fetch(PDO::FETCH_ASSOC)['total_scans'];
    }

    public static function getDetailedScanStats($user_id = null, $days = 30) {
        $database = new Database();
        $db = $database->getConnection();

        $where_clause = $user_id ? "WHERE p.user_id = ?" : "";
        $params = $user_id ? [$user_id] : [];

        // Get scan statistics
        $stats_query = "SELECT 
                            COUNT(*) as total_scans,
                            COUNT(DISTINCT qs.product_id) as products_scanned,
                            COUNT(DISTINCT DATE(qs.scanned_at)) as active_days,
                            AVG(daily_scans.scan_count) as avg_daily_scans
                        FROM qr_scans qs 
                        JOIN products p ON qs.product_id = p.id 
                        LEFT JOIN (
                            SELECT DATE(scanned_at) as scan_date, COUNT(*) as scan_count
                            FROM qr_scans qs2
                            JOIN products p2 ON qs2.product_id = p2.id
                            " . ($user_id ? "WHERE p2.user_id = ?" : "") . "
                            WHERE qs2.scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                            GROUP BY DATE(scanned_at)
                        ) daily_scans ON DATE(qs.scanned_at) = daily_scans.scan_date
                        $where_clause
                        AND qs.scanned_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stats_params = $user_id ? [$user_id, $days, $user_id, $days] : [$days, $days];
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->execute($stats_params);
        
        return $stats_stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
