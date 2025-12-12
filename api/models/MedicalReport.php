<?php
/**
 * Medical Report Model
 */

class MedicalReport {
    private $conn;
    private $table = 'medical_reports';

    public $id;
    public $user_id;
    public $report_type;
    public $report_date;
    public $file_path;
    public $ocr_text;
    public $parsed_data;
    public $abnormalities;
    public $recommendations;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create new report
     */
    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (user_id, report_type, report_date, file_path, ocr_text, parsed_data, abnormalities, recommendations) 
                  VALUES (:user_id, :report_type, :report_date, :file_path, :ocr_text, :parsed_data, :abnormalities, :recommendations)";

        $stmt = $this->conn->prepare($query);
        
        $stmt->bindParam(':user_id', $this->user_id);
        $stmt->bindParam(':report_type', $this->report_type);
        $stmt->bindParam(':report_date', $this->report_date);
        $stmt->bindParam(':file_path', $this->file_path);
        $stmt->bindParam(':ocr_text', $this->ocr_text);
        $stmt->bindParam(':parsed_data', $this->parsed_data);
        $stmt->bindParam(':abnormalities', $this->abnormalities);
        $stmt->bindParam(':recommendations', $this->recommendations);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Get all reports for a user
     */
    public function getByUser($userId, $reportType = null) {
        $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id";
        
        if ($reportType) {
            $query .= " AND report_type = :report_type";
        }
        
        $query .= " ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        
        if ($reportType) {
            $stmt->bindParam(':report_type', $reportType);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get report by ID
     */
    public function getById($id, $userId = null) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :id";
        
        if ($userId) {
            $query .= " AND user_id = :user_id";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if ($userId) {
            $stmt->bindParam(':user_id', $userId);
        }
        
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * Update report with OCR results
     */
    public function updateOcrResults($id, $ocrText, $parsedData, $abnormalities, $recommendations) {
        $query = "UPDATE " . $this->table . " 
                  SET ocr_text = :ocr_text, parsed_data = :parsed_data, 
                      abnormalities = :abnormalities, recommendations = :recommendations 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':ocr_text', $ocrText);
        $stmt->bindParam(':parsed_data', $parsedData);
        $stmt->bindParam(':abnormalities', $abnormalities);
        $stmt->bindParam(':recommendations', $recommendations);
        $stmt->bindParam(':id', $id);

        return $stmt->execute();
    }

    /**
     * Delete report
     */
    public function delete($id, $userId) {
        // Get file path first
        $report = $this->getById($id, $userId);
        if ($report && $report['file_path'] && file_exists($report['file_path'])) {
            unlink($report['file_path']);
        }

        $query = "DELETE FROM " . $this->table . " WHERE id = :id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':user_id', $userId);

        return $stmt->execute();
    }

    /**
     * Save extracted values
     */
    public function saveReportValues($reportId, $values) {
        $query = "INSERT INTO report_values 
                  (report_id, parameter_name, value, unit, reference_min, reference_max, is_abnormal) 
                  VALUES (:report_id, :param, :value, :unit, :ref_min, :ref_max, :abnormal)";

        $stmt = $this->conn->prepare($query);

        foreach ($values as $value) {
            $stmt->bindParam(':report_id', $reportId);
            $stmt->bindParam(':param', $value['parameter']);
            $stmt->bindParam(':value', $value['value']);
            $stmt->bindParam(':unit', $value['unit']);
            $stmt->bindParam(':ref_min', $value['reference_min']);
            $stmt->bindParam(':ref_max', $value['reference_max']);
            $stmt->bindParam(':abnormal', $value['is_abnormal']);
            $stmt->execute();
        }

        return true;
    }

    /**
     * Get report values
     */
    public function getReportValues($reportId) {
        $query = "SELECT * FROM report_values WHERE report_id = :report_id ORDER BY parameter_name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':report_id', $reportId);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get abnormal values history for a user
     */
    public function getAbnormalHistory($userId) {
        $query = "SELECT rv.*, mr.report_type, mr.report_date 
                  FROM report_values rv
                  JOIN medical_reports mr ON rv.report_id = mr.id
                  WHERE mr.user_id = :user_id AND rv.is_abnormal = 1
                  ORDER BY mr.report_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get parameter trend over time
     */
    public function getParameterTrend($userId, $parameterName) {
        $query = "SELECT rv.value, rv.unit, mr.report_date 
                  FROM report_values rv
                  JOIN medical_reports mr ON rv.report_id = mr.id
                  WHERE mr.user_id = :user_id AND rv.parameter_name = :param
                  ORDER BY mr.report_date ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':param', $parameterName);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
