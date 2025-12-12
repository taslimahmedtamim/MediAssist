<?php
/**
 * Medical Reports API Endpoints
 * Handles report upload, OCR processing, and analysis
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/MedicalReport.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    sendError('Database connection failed', 500);
}

$report = new MedicalReport($db);
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'POST':
        if ($action === 'upload') {
            // Handle file upload
            if (!isset($_FILES['report']) || !isset($_POST['user_id'])) {
                sendError('Report file and user ID required');
            }

            $file = $_FILES['report'];
            $userId = $_POST['user_id'];
            $reportType = $_POST['report_type'] ?? 'other';
            $reportDate = $_POST['report_date'] ?? date('Y-m-d');

            // Validate file
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            if (!in_array($file['type'], $allowedTypes)) {
                sendError('Invalid file type. Allowed: JPEG, PNG, GIF, PDF');
            }

            if ($file['size'] > MAX_FILE_SIZE) {
                sendError('File too large. Maximum size: 10MB');
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'report_' . $userId . '_' . time() . '.' . $extension;
            $filepath = REPORTS_DIR . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                sendError('Failed to save file', 500);
            }

            // Create report record
            $report->user_id = $userId;
            $report->report_type = $reportType;
            $report->report_date = $reportDate;
            $report->file_path = $filepath;
            $report->ocr_text = null;
            $report->parsed_data = null;
            $report->abnormalities = null;
            $report->recommendations = null;

            if ($report->create()) {
                // Send to OCR service for processing
                $ocrResult = processWithOCR($filepath, $reportType);
                
                if ($ocrResult['success']) {
                    $report->updateOcrResults(
                        $report->id,
                        $ocrResult['ocr_text'],
                        json_encode($ocrResult['parsed_data']),
                        json_encode($ocrResult['abnormalities']),
                        $ocrResult['recommendations']
                    );

                    // Save extracted values
                    if (!empty($ocrResult['values'])) {
                        $report->saveReportValues($report->id, $ocrResult['values']);
                    }
                }

                sendResponse([
                    'success' => true,
                    'message' => 'Report uploaded and processed',
                    'report_id' => $report->id,
                    'ocr_result' => $ocrResult
                ], 201);
            } else {
                // Clean up file if database insert fails
                unlink($filepath);
                sendError('Failed to save report', 500);
            }
        }
        elseif ($action === 'analyze') {
            // Re-analyze existing report
            $data = getJsonInput();
            
            if (!isset($data['report_id'])) {
                sendError('Report ID required');
            }

            $reportData = $report->getById($data['report_id']);
            if (!$reportData) {
                sendError('Report not found', 404);
            }

            $ocrResult = processWithOCR($reportData['file_path'], $reportData['report_type']);
            
            if ($ocrResult['success']) {
                $report->updateOcrResults(
                    $data['report_id'],
                    $ocrResult['ocr_text'],
                    json_encode($ocrResult['parsed_data']),
                    json_encode($ocrResult['abnormalities']),
                    $ocrResult['recommendations']
                );

                sendResponse([
                    'success' => true,
                    'message' => 'Report re-analyzed',
                    'ocr_result' => $ocrResult
                ]);
            } else {
                sendError('OCR processing failed: ' . $ocrResult['error'], 500);
            }
        }
        break;

    case 'GET':
        $userId = $_GET['user_id'] ?? null;
        
        if ($action === 'single') {
            $reportId = $_GET['report_id'] ?? null;
            if (!$reportId) {
                sendError('Report ID required');
            }

            $reportData = $report->getById($reportId, $userId);
            if ($reportData) {
                $reportData['values'] = $report->getReportValues($reportId);
                $reportData['parsed_data'] = json_decode($reportData['parsed_data'], true);
                $reportData['abnormalities'] = json_decode($reportData['abnormalities'], true);
                sendResponse(['success' => true, 'report' => $reportData]);
            } else {
                sendError('Report not found', 404);
            }
        }
        elseif ($action === 'abnormal_history') {
            if (!$userId) {
                sendError('User ID required');
            }
            
            $history = $report->getAbnormalHistory($userId);
            sendResponse(['success' => true, 'abnormal_history' => $history]);
        }
        elseif ($action === 'parameter_trend') {
            if (!$userId) {
                sendError('User ID required');
            }
            
            $parameter = $_GET['parameter'] ?? null;
            if (!$parameter) {
                sendError('Parameter name required');
            }
            
            $trend = $report->getParameterTrend($userId, $parameter);
            sendResponse(['success' => true, 'parameter' => $parameter, 'trend' => $trend]);
        }
        elseif ($action === 'values') {
            $reportId = $_GET['report_id'] ?? null;
            if (!$reportId) {
                sendError('Report ID required');
            }
            
            $values = $report->getReportValues($reportId);
            sendResponse(['success' => true, 'values' => $values]);
        }
        else {
            // Get all reports for user
            if (!$userId) {
                sendError('User ID required');
            }
            
            $reportType = $_GET['report_type'] ?? null;
            $reports = $report->getByUser($userId, $reportType);
            
            sendResponse(['success' => true, 'reports' => $reports]);
        }
        break;

    case 'DELETE':
        $reportId = $_GET['report_id'] ?? null;
        $userId = $_GET['user_id'] ?? null;
        
        if (!$reportId || !$userId) {
            sendError('Report ID and User ID required');
        }

        if ($report->delete($reportId, $userId)) {
            sendResponse(['success' => true, 'message' => 'Report deleted successfully']);
        } else {
            sendError('Delete failed', 500);
        }
        break;

    default:
        sendError('Method not allowed', 405);
}

/**
 * Process report with OCR service
 */
function processWithOCR($filepath, $reportType) {
    // Try to call Python OCR service
    $ocrUrl = OCR_SERVICE_URL . '/analyze';
    
    $postData = [
        'file_path' => $filepath,
        'report_type' => $reportType
    ];

    $ch = curl_init($ocrUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        if ($result && isset($result['success']) && $result['success']) {
            return $result;
        }
    }

    // Fallback: return placeholder if OCR service is unavailable
    return [
        'success' => true,
        'ocr_text' => 'OCR service unavailable. Please ensure the Python OCR service is running.',
        'parsed_data' => [],
        'abnormalities' => [],
        'values' => [],
        'recommendations' => 'OCR service is not available. Please start the OCR microservice to analyze reports.',
        'warning' => 'OCR service not available'
    ];
}
