# MediAssist+ OCR Service

## Requirements

1. **Python 3.8+**
2. **Tesseract OCR** - Install from: https://github.com/UB-Mannheim/tesseract/wiki
3. **Poppler** (for PDF support) - Install from: https://github.com/oschwartz10612/poppler-windows/releases

## Installation

### Windows Setup

1. Install Tesseract OCR:
   - Download from: https://github.com/UB-Mannheim/tesseract/wiki
   - Add to PATH or update the path in `app.py`

2. Install Poppler for PDF support:
   - Download from: https://github.com/oschwartz10612/poppler-windows/releases
   - Add `bin` folder to PATH

3. Install Python dependencies:
   ```bash
   cd ocr_service
   pip install -r requirements.txt
   ```

4. Run the service:
   ```bash
   python app.py
   ```

The OCR service will start on `http://localhost:5000`

## API Endpoints

- `GET /health` - Health check
- `POST /analyze` - Analyze medical report (JSON: file_path, report_type)
- `POST /extract-text` - Extract text from image
- `GET /reference-ranges` - Get all reference ranges
- `GET /supported-reports` - Get supported report types

## Supported Report Types

- CBC (Complete Blood Count)
- Kidney Function
- Lipid Profile
- Liver Function
- Diabetes
- Thyroid
