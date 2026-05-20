<?php
/**
 * Response Helper
 * JSON and redirect helpers
 */

require_once __DIR__ . '/../config/config.php';

class Response {
    
    /**
     * Send JSON response
     */
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    /**
     * Send success JSON response
     */
    public static function success($message = 'Success', $data = null, $statusCode = 200) {
        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }
    
    /**
     * Send error JSON response
     */
    public static function error($message = 'Error', $data = null, $statusCode = 400) {
        return self::json([
            'success' => false,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }
    
    /**
     * Send validation error response
     */
    public static function validationError($errors, $statusCode = 422) {
        return self::json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $errors
        ], $statusCode);
    }
    
    /**
     * Redirect to URL
     */
    public static function redirect($url, $statusCode = 302) {
        header("Location: $url", true, $statusCode);
        exit;
    }
    
    /**
     * Redirect back with flash message
     */
    public static function redirectBack($type = 'success', $message = '') {
        if ($message) {
            Session::setFlash($type, $message);
        }
        
        $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
        self::redirect($referer);
    }
    
    /**
     * Redirect with flash message
     */
    public static function redirectTo($url, $type = 'success', $message = '') {
        if ($message) {
            Session::setFlash($type, $message);
        }
        self::redirect($url);
    }
    
    /**
     * Download file
     */
    public static function download($filename, $content, $mimeType = 'application/octet-stream') {
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }
    
    /**
     * Force download for PDF
     */
    public static function downloadPDF($filename, $content) {
        return self::download($filename, $content, 'application/pdf');
    }
    
    /**
     * Force download for CSV
     */
    public static function downloadCSV($filename, $content) {
        return self::download($filename, $content, 'text/csv');
    }
}
