"""
MediAssist+ OCR Microservice
Python Flask service for medical report analysis using Tesseract OCR
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import pytesseract
from PIL import Image
import re
import json
import os
from pdf2image import convert_from_path
import logging

app = Flask(__name__)
CORS(app)

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Tesseract configuration (adjust path for Windows if needed)
# pytesseract.pytesseract.tesseract_cmd = r'C:\Program Files\Tesseract-OCR\tesseract.exe'

# Reference ranges for common lab parameters
REFERENCE_RANGES = {
    # CBC Parameters
    'hemoglobin': {'min': 12.0, 'max': 17.5, 'unit': 'g/dL', 'aliases': ['hb', 'hgb', 'haemoglobin']},
    'hematocrit': {'min': 36.0, 'max': 50.0, 'unit': '%', 'aliases': ['hct', 'pcv', 'packed cell volume']},
    'rbc': {'min': 4.0, 'max': 6.0, 'unit': 'million/μL', 'aliases': ['red blood cells', 'erythrocytes', 'rbc count']},
    'wbc': {'min': 4000, 'max': 11000, 'unit': '/μL', 'aliases': ['white blood cells', 'leukocytes', 'wbc count', 'tlc']},
    'platelets': {'min': 150000, 'max': 400000, 'unit': '/μL', 'aliases': ['platelet count', 'plt', 'thrombocytes']},
    'mcv': {'min': 80, 'max': 100, 'unit': 'fL', 'aliases': ['mean corpuscular volume']},
    'mch': {'min': 27, 'max': 33, 'unit': 'pg', 'aliases': ['mean corpuscular hemoglobin']},
    'mchc': {'min': 32, 'max': 36, 'unit': 'g/dL', 'aliases': ['mean corpuscular hemoglobin concentration']},
    'rdw': {'min': 11.5, 'max': 14.5, 'unit': '%', 'aliases': ['red cell distribution width']},
    'neutrophils': {'min': 40, 'max': 70, 'unit': '%', 'aliases': ['neutrophil', 'neut']},
    'lymphocytes': {'min': 20, 'max': 40, 'unit': '%', 'aliases': ['lymphocyte', 'lymph']},
    'monocytes': {'min': 2, 'max': 8, 'unit': '%', 'aliases': ['monocyte', 'mono']},
    'eosinophils': {'min': 1, 'max': 4, 'unit': '%', 'aliases': ['eosinophil', 'eos']},
    'basophils': {'min': 0, 'max': 1, 'unit': '%', 'aliases': ['basophil', 'baso']},
    
    # Kidney Function
    'creatinine': {'min': 0.6, 'max': 1.2, 'unit': 'mg/dL', 'aliases': ['serum creatinine', 'creat']},
    'bun': {'min': 7, 'max': 20, 'unit': 'mg/dL', 'aliases': ['blood urea nitrogen', 'urea nitrogen']},
    'urea': {'min': 15, 'max': 45, 'unit': 'mg/dL', 'aliases': ['blood urea', 'serum urea']},
    'uric_acid': {'min': 3.5, 'max': 7.2, 'unit': 'mg/dL', 'aliases': ['uric acid', 'serum uric acid']},
    'egfr': {'min': 90, 'max': 120, 'unit': 'mL/min/1.73m²', 'aliases': ['estimated gfr', 'gfr', 'glomerular filtration rate']},
    'sodium': {'min': 136, 'max': 145, 'unit': 'mEq/L', 'aliases': ['na', 'serum sodium']},
    'potassium': {'min': 3.5, 'max': 5.0, 'unit': 'mEq/L', 'aliases': ['k', 'serum potassium']},
    'chloride': {'min': 98, 'max': 106, 'unit': 'mEq/L', 'aliases': ['cl', 'serum chloride']},
    'calcium': {'min': 8.5, 'max': 10.5, 'unit': 'mg/dL', 'aliases': ['ca', 'serum calcium']},
    'phosphorus': {'min': 2.5, 'max': 4.5, 'unit': 'mg/dL', 'aliases': ['phosphate', 'serum phosphorus']},
    
    # Lipid Profile
    'total_cholesterol': {'min': 0, 'max': 200, 'unit': 'mg/dL', 'aliases': ['cholesterol', 'tc', 'total chol']},
    'ldl': {'min': 0, 'max': 100, 'unit': 'mg/dL', 'aliases': ['ldl cholesterol', 'ldl-c', 'bad cholesterol']},
    'hdl': {'min': 40, 'max': 60, 'unit': 'mg/dL', 'aliases': ['hdl cholesterol', 'hdl-c', 'good cholesterol']},
    'triglycerides': {'min': 0, 'max': 150, 'unit': 'mg/dL', 'aliases': ['tg', 'trigs', 'triglyceride']},
    'vldl': {'min': 5, 'max': 40, 'unit': 'mg/dL', 'aliases': ['vldl cholesterol']},
    
    # Liver Function
    'alt': {'min': 7, 'max': 56, 'unit': 'U/L', 'aliases': ['sgpt', 'alanine aminotransferase', 'alanine transaminase']},
    'ast': {'min': 10, 'max': 40, 'unit': 'U/L', 'aliases': ['sgot', 'aspartate aminotransferase', 'aspartate transaminase']},
    'alp': {'min': 44, 'max': 147, 'unit': 'U/L', 'aliases': ['alkaline phosphatase']},
    'ggt': {'min': 9, 'max': 48, 'unit': 'U/L', 'aliases': ['gamma gt', 'gamma glutamyl transferase']},
    'bilirubin_total': {'min': 0.1, 'max': 1.2, 'unit': 'mg/dL', 'aliases': ['total bilirubin', 'tbil', 't.bil']},
    'bilirubin_direct': {'min': 0, 'max': 0.3, 'unit': 'mg/dL', 'aliases': ['direct bilirubin', 'dbil', 'd.bil', 'conjugated bilirubin']},
    'bilirubin_indirect': {'min': 0.1, 'max': 0.9, 'unit': 'mg/dL', 'aliases': ['indirect bilirubin', 'unconjugated bilirubin']},
    'albumin': {'min': 3.5, 'max': 5.0, 'unit': 'g/dL', 'aliases': ['serum albumin', 'alb']},
    'total_protein': {'min': 6.0, 'max': 8.3, 'unit': 'g/dL', 'aliases': ['tp', 'serum protein']},
    
    # Diabetes
    'glucose_fasting': {'min': 70, 'max': 100, 'unit': 'mg/dL', 'aliases': ['fasting glucose', 'fbs', 'fasting blood sugar', 'fbg']},
    'glucose_pp': {'min': 70, 'max': 140, 'unit': 'mg/dL', 'aliases': ['postprandial glucose', 'ppbs', 'pp glucose', 'ppbg']},
    'hba1c': {'min': 4.0, 'max': 5.6, 'unit': '%', 'aliases': ['glycated hemoglobin', 'a1c', 'glycohemoglobin', 'hemoglobin a1c']},
    
    # Thyroid
    'tsh': {'min': 0.4, 'max': 4.0, 'unit': 'mIU/L', 'aliases': ['thyroid stimulating hormone']},
    't3': {'min': 80, 'max': 200, 'unit': 'ng/dL', 'aliases': ['triiodothyronine', 'total t3']},
    't4': {'min': 5.0, 'max': 12.0, 'unit': 'μg/dL', 'aliases': ['thyroxine', 'total t4']},
    'free_t3': {'min': 2.3, 'max': 4.2, 'unit': 'pg/mL', 'aliases': ['ft3']},
    'free_t4': {'min': 0.8, 'max': 1.8, 'unit': 'ng/dL', 'aliases': ['ft4']}
}

# Specialty recommendations based on abnormal parameters
SPECIALTY_RECOMMENDATIONS = {
    'cbc': {
        'specialist': 'Hematologist',
        'conditions': ['Anemia', 'Leukemia', 'Thrombocytopenia', 'Polycythemia'],
        'parameters': ['hemoglobin', 'hematocrit', 'rbc', 'wbc', 'platelets', 'mcv', 'mch', 'mchc']
    },
    'kidney': {
        'specialist': 'Nephrologist',
        'conditions': ['Chronic Kidney Disease', 'Acute Kidney Injury', 'Electrolyte Imbalance'],
        'parameters': ['creatinine', 'bun', 'urea', 'egfr', 'sodium', 'potassium', 'calcium', 'phosphorus']
    },
    'lipid': {
        'specialist': 'Cardiologist',
        'conditions': ['Hyperlipidemia', 'Cardiovascular Disease Risk', 'Metabolic Syndrome'],
        'parameters': ['total_cholesterol', 'ldl', 'hdl', 'triglycerides', 'vldl']
    },
    'liver': {
        'specialist': 'Hepatologist/Gastroenterologist',
        'conditions': ['Liver Disease', 'Hepatitis', 'Cirrhosis', 'Fatty Liver'],
        'parameters': ['alt', 'ast', 'alp', 'ggt', 'bilirubin_total', 'albumin']
    },
    'diabetes': {
        'specialist': 'Endocrinologist',
        'conditions': ['Diabetes Mellitus', 'Prediabetes', 'Hypoglycemia'],
        'parameters': ['glucose_fasting', 'glucose_pp', 'hba1c']
    },
    'thyroid': {
        'specialist': 'Endocrinologist',
        'conditions': ['Hypothyroidism', 'Hyperthyroidism', 'Thyroiditis'],
        'parameters': ['tsh', 't3', 't4', 'free_t3', 'free_t4']
    }
}


def extract_text_from_image(image_path):
    """Extract text from image using Tesseract OCR"""
    try:
        if image_path.lower().endswith('.pdf'):
            # Convert PDF to images
            images = convert_from_path(image_path)
            text = ""
            for img in images:
                text += pytesseract.image_to_string(img) + "\n"
        else:
            image = Image.open(image_path)
            text = pytesseract.image_to_string(image)
        return text
    except Exception as e:
        logger.error(f"OCR extraction error: {e}")
        return None


def parse_lab_values(text, report_type):
    """Parse lab values from OCR text"""
    values = []
    text_lower = text.lower()
    
    # Determine which parameters to look for based on report type
    if report_type == 'cbc':
        target_params = ['hemoglobin', 'hematocrit', 'rbc', 'wbc', 'platelets', 'mcv', 'mch', 'mchc', 'rdw',
                        'neutrophils', 'lymphocytes', 'monocytes', 'eosinophils', 'basophils']
    elif report_type == 'kidney':
        target_params = ['creatinine', 'bun', 'urea', 'uric_acid', 'egfr', 'sodium', 'potassium', 
                        'chloride', 'calcium', 'phosphorus']
    elif report_type == 'lipid':
        target_params = ['total_cholesterol', 'ldl', 'hdl', 'triglycerides', 'vldl']
    elif report_type == 'liver':
        target_params = ['alt', 'ast', 'alp', 'ggt', 'bilirubin_total', 'bilirubin_direct', 
                        'bilirubin_indirect', 'albumin', 'total_protein']
    elif report_type == 'diabetes':
        target_params = ['glucose_fasting', 'glucose_pp', 'hba1c']
    elif report_type == 'thyroid':
        target_params = ['tsh', 't3', 't4', 'free_t3', 'free_t4']
    else:
        target_params = list(REFERENCE_RANGES.keys())
    
    for param in target_params:
        ref = REFERENCE_RANGES.get(param, {})
        search_terms = [param] + ref.get('aliases', [])
        
        for term in search_terms:
            # Pattern to find parameter name followed by value
            patterns = [
                rf'{re.escape(term)}\s*[:\-]?\s*([\d.]+)',
                rf'{re.escape(term)}\s+(\d+\.?\d*)',
                rf'(\d+\.?\d*)\s*{re.escape(term)}'
            ]
            
            for pattern in patterns:
                match = re.search(pattern, text_lower)
                if match:
                    try:
                        value = float(match.group(1))
                        is_abnormal = False
                        
                        if 'min' in ref and 'max' in ref:
                            is_abnormal = value < ref['min'] or value > ref['max']
                        
                        values.append({
                            'parameter': param.replace('_', ' ').title(),
                            'value': value,
                            'unit': ref.get('unit', ''),
                            'reference_min': ref.get('min'),
                            'reference_max': ref.get('max'),
                            'is_abnormal': is_abnormal
                        })
                        break
                    except ValueError:
                        continue
            else:
                continue
            break
    
    return values


def identify_abnormalities(values):
    """Identify abnormal values and their implications"""
    abnormalities = []
    
    for val in values:
        if val['is_abnormal']:
            direction = 'high' if val['value'] > val['reference_max'] else 'low'
            abnormalities.append({
                'parameter': val['parameter'],
                'value': val['value'],
                'unit': val['unit'],
                'direction': direction,
                'reference_range': f"{val['reference_min']} - {val['reference_max']}",
                'severity': calculate_severity(val)
            })
    
    return abnormalities


def calculate_severity(value_data):
    """Calculate severity of abnormal value"""
    val = value_data['value']
    ref_min = value_data['reference_min']
    ref_max = value_data['reference_max']
    
    if val < ref_min:
        deviation = (ref_min - val) / ref_min * 100
    else:
        deviation = (val - ref_max) / ref_max * 100
    
    if deviation < 10:
        return 'mild'
    elif deviation < 25:
        return 'moderate'
    else:
        return 'severe'


def generate_recommendations(abnormalities, report_type):
    """Generate recommendations based on abnormalities"""
    recommendations = []
    specialists_needed = set()
    
    if not abnormalities:
        return "All values appear to be within normal ranges. Continue with regular health checkups."
    
    # Add report-type specific specialist
    if report_type in SPECIALTY_RECOMMENDATIONS:
        spec_info = SPECIALTY_RECOMMENDATIONS[report_type]
        specialists_needed.add(spec_info['specialist'])
    
    # Generate specific recommendations based on abnormal values
    for abnorm in abnormalities:
        param = abnorm['parameter'].lower().replace(' ', '_')
        severity = abnorm['severity']
        direction = abnorm['direction']
        
        # Add relevant specialist based on parameter
        for report_cat, spec_info in SPECIALTY_RECOMMENDATIONS.items():
            if param in spec_info['parameters']:
                specialists_needed.add(spec_info['specialist'])
        
        # Generate parameter-specific advice
        if param in ['hemoglobin', 'hematocrit', 'rbc']:
            if direction == 'low':
                recommendations.append(f"Low {abnorm['parameter']} may indicate anemia. Consider iron-rich foods and vitamin B12 supplementation.")
        elif param in ['wbc']:
            recommendations.append(f"Abnormal WBC count ({direction}) may indicate infection or immune system issues.")
        elif param in ['creatinine', 'bun', 'urea']:
            if direction == 'high':
                recommendations.append(f"Elevated {abnorm['parameter']} may indicate kidney function issues. Stay hydrated and limit protein intake.")
        elif param in ['glucose_fasting', 'glucose_pp', 'hba1c']:
            if direction == 'high':
                recommendations.append(f"Elevated blood sugar levels detected. Consider dietary modifications and regular exercise.")
        elif param in ['total_cholesterol', 'ldl', 'triglycerides']:
            if direction == 'high':
                recommendations.append(f"High {abnorm['parameter']} increases cardiovascular risk. Consider a heart-healthy diet low in saturated fats.")
        elif param in ['alt', 'ast']:
            if direction == 'high':
                recommendations.append(f"Elevated liver enzymes detected. Avoid alcohol and fatty foods.")
    
    # Build final recommendation text
    rec_text = "RECOMMENDATIONS:\n\n"
    
    if specialists_needed:
        rec_text += f"🏥 Suggested Specialists: {', '.join(specialists_needed)}\n\n"
    
    rec_text += "📋 Health Advice:\n"
    for i, rec in enumerate(recommendations[:5], 1):
        rec_text += f"  {i}. {rec}\n"
    
    if any(a['severity'] == 'severe' for a in abnormalities):
        rec_text += "\n⚠️ IMPORTANT: Some values show significant deviation from normal. Please consult a healthcare provider soon."
    
    return rec_text


@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({'status': 'healthy', 'service': 'MediAssist+ OCR Service'})


@app.route('/analyze', methods=['POST'])
def analyze_report():
    """Main endpoint to analyze medical reports"""
    try:
        data = request.json
        file_path = data.get('file_path')
        report_type = data.get('report_type', 'other')
        
        if not file_path or not os.path.exists(file_path):
            return jsonify({
                'success': False,
                'error': 'File not found'
            }), 400
        
        # Extract text using OCR
        ocr_text = extract_text_from_image(file_path)
        
        if not ocr_text:
            return jsonify({
                'success': False,
                'error': 'Failed to extract text from image'
            }), 500
        
        # Parse lab values
        values = parse_lab_values(ocr_text, report_type)
        
        # Identify abnormalities
        abnormalities = identify_abnormalities(values)
        
        # Generate recommendations
        recommendations = generate_recommendations(abnormalities, report_type)
        
        # Prepare parsed data summary
        parsed_data = {
            'report_type': report_type,
            'total_parameters_found': len(values),
            'abnormal_count': len(abnormalities),
            'parameters': [v['parameter'] for v in values]
        }
        
        return jsonify({
            'success': True,
            'ocr_text': ocr_text,
            'parsed_data': parsed_data,
            'values': values,
            'abnormalities': abnormalities,
            'recommendations': recommendations
        })
        
    except Exception as e:
        logger.error(f"Analysis error: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500


@app.route('/extract-text', methods=['POST'])
def extract_text():
    """Simple endpoint to just extract text from image"""
    try:
        if 'file' in request.files:
            file = request.files['file']
            # Save temporarily
            temp_path = f"/tmp/{file.filename}"
            file.save(temp_path)
            text = extract_text_from_image(temp_path)
            os.remove(temp_path)
        elif 'file_path' in request.json:
            text = extract_text_from_image(request.json['file_path'])
        else:
            return jsonify({'success': False, 'error': 'No file provided'}), 400
        
        return jsonify({
            'success': True,
            'text': text
        })
        
    except Exception as e:
        logger.error(f"Text extraction error: {e}")
        return jsonify({
            'success': False,
            'error': str(e)
        }), 500


@app.route('/reference-ranges', methods=['GET'])
def get_reference_ranges():
    """Get all reference ranges"""
    return jsonify({
        'success': True,
        'reference_ranges': REFERENCE_RANGES
    })


@app.route('/supported-reports', methods=['GET'])
def get_supported_reports():
    """Get list of supported report types"""
    return jsonify({
        'success': True,
        'report_types': list(SPECIALTY_RECOMMENDATIONS.keys()),
        'specialists': {k: v['specialist'] for k, v in SPECIALTY_RECOMMENDATIONS.items()}
    })


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)
