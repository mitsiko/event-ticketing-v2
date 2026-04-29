<?php
require_once __DIR__ . '/../../includes/header.php'; 

$ticket_code = isset($_GET['code']) ? trim($_GET['code']) : '';
$redirect_action = isset($_GET['redirect']) ? trim($_GET['redirect']) : '';
$validation_result = null;

// If redirected from the public lookup, go straight to view
if (!empty($ticket_code) && $redirect_action === 'view') {
    // Look up the ticket by code
    $lookupQuery = "
        SELECT t.ticket_id 
        FROM Ticket t 
        WHERE t.ticket_code = '" . mysqli_real_escape_string($conn, $ticket_code) . "'
    ";
    $lookupResult = mysqli_query($conn, $lookupQuery);
    
    if ($lookupResult && mysqli_num_rows($lookupResult) > 0) {
        $ticket = mysqli_fetch_assoc($lookupResult);
        // Redirect to ticket view page
        header('Location: /event-ticketing-v2/modules/tickets/view.php?id=' . $ticket['ticket_id'] . '&public=1');
        exit;
    } else {
        // Ticket not found - show error on this page
        $validation_result = [
            'success' => false,
            'message' => 'Invalid ticket code. Ticket not found.'
        ];
    }
}

// ... rest of the existing validate.php code continues below
$validation_result = null;

if (!empty($ticket_code)) {
    $python_script = __DIR__ . '/../../python/validate_ticket.py';
    
    if (!file_exists($python_script)) {
        $validation_result = [
            'success' => false,
            'message' => 'Python validation script not found.'
        ];
    } else {
        $ticket_code_escaped = escapeshellarg($ticket_code);
        $command = "py \"$python_script\" $ticket_code_escaped 2>&1";
        $raw_output = shell_exec($command);
        
        $lines = explode("\n", trim($raw_output));
        $json_line = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '{') === 0 || strpos($line, '{"') === 0) {
                $json_line = $line;
                break;
            }
        }
        
        if (!empty($json_line)) {
            $validation_result = json_decode($json_line, true);
        }
        
        if ($validation_result === null) {
            $validation_result = [
                'success' => false,
                'message' => 'Error processing validation: Invalid response.',
                'debug_raw' => $raw_output
            ];
        }
    }
}

$recent_sql = "SELECT t.ticket_code, a.first_name, a.last_name, t.is_validated
               FROM Ticket t
               JOIN Attendee a ON t.attendee_id = a.attendee_id
               ORDER BY t.purchase_date DESC
               LIMIT 5";
$recent = mysqli_query($conn, $recent_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <style>
        #camera-container {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        #camera-box {
            background: var(--color-background-primary);
            border-radius: var(--border-radius-lg);
            padding: 20px;
            max-width: 500px;
            width: 90%;
            position: relative;
        }
        
        #camera-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        #camera-header h3 {
            font-size: 16px;
            font-weight: 500;
            margin: 0;
        }
        
        #camera-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--color-text-secondary);
            padding: 0;
            line-height: 1;
        }
        
        #camera-close:hover {
            color: var(--color-text-primary);
        }
        
        #qr-reader {
            width: 100%;
            border-radius: var(--border-radius-md);
            overflow: hidden;
            min-height: 300px;
        }
        
        #qr-reader video {
            width: 100%;
            border-radius: var(--border-radius-md);
        }
        
        #camera-error {
            color: var(--color-text-danger);
            font-size: 13px;
            margin-top: 10px;
            display: none;
        }
        
        #camera-loading {
            text-align: center;
            padding: 20px;
            color: var(--color-text-secondary);
        }
        
        .scan-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--color-background-primary);
            border: 0.5px solid var(--color-border-secondary);
            border-radius: var(--border-radius-md);
            padding: 6px 14px;
            font-size: 13px;
            cursor: pointer;
            color: var(--color-text-primary);
            transition: all 0.1s;
        }
        
        .scan-btn:hover {
            background: var(--color-background-secondary);
        }
        
        .scan-btn .camera-icon {
            font-size: 16px;
        }

        #qr-file-input {
            display: none;
        }
        
        .page-header {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
            justify-content: space-between;
        }
    </style>
</head>
<body>

<div class="page active">
    <div class="page-header">
        <div class="page-header-left">
            <div>
                <div class="page-title">Ticket Validation</div>
                <div class="page-sub">Scan or enter ticket code to validate entry</div>
            </div>
            <div style="display:flex;gap:8px">
                <button class="scan-btn" id="open-camera-btn" onclick="openCameraScanner()">
                    <span class="camera-icon">📷</span>
                    Scan QR
                </button>
                <button class="scan-btn" id="upload-qr-btn" onclick="openQRUpload()">
                    <span class="camera-icon">📁</span>
                    Upload QR
                </button>
                <input type="file" id="qr-file-input" accept="image/*,.pdf,.html" onchange="handleQRFileUpload(event)">
            </div>
        </div>
    </div>

    <div style="max-width:100%">
        <div class="card">
            <div style="font-size:13px;font-weight:500;margin-bottom:12px">Enter ticket code</div>
            <form method="GET" style="display:flex;gap:8px">
                <input type="text" name="code" id="ticket-code-input" value="<?php echo h($ticket_code); ?>" placeholder="e.g. UUID ticket code..." style="flex:1" required>
                <button type="submit" class="btn btn-primary">Validate</button>
                <a href="/event-ticketing-v2/modules/tickets/validate.php" class="btn">Clear</a>
            </form>
            
            <?php if ($validation_result): ?>
                <?php if ($validation_result['success']): ?>
                    <div style="background:#EAF3DE;border:0.5px solid #639922;color:#27500A;padding:1rem;border-radius:6px;margin-top:16px">
                        <div style="font-weight:500;margin-bottom:6px">✓ <?php echo h($validation_result['message']); ?></div>
                        <div>
                            <?php if (isset($validation_result['ticket'])): ?>
                                <table style="font-size:13px;width:100%">
                                    <tr><td style="padding:2px 0;width:100px">Attendee</td><td><strong><?php echo h($validation_result['ticket']['attendee_name']); ?></strong></td></tr>
                                    <tr><td style="padding:2px 0">Type</td><td><?php echo h($validation_result['ticket']['attendee_type']); ?></td></tr>
                                    <tr><td style="padding:2px 0">Event</td><td><?php echo h($validation_result['ticket']['event_name']); ?></td></tr>
                                    <tr><td style="padding:2px 0">Category</td><td><?php echo h($validation_result['ticket']['category_name']); ?></td></tr>
                                    <tr><td style="padding:2px 0">Venue</td><td><?php echo h($validation_result['ticket']['venue_name']); ?></td></tr>
                                    <tr><td style="padding:2px 0">Validated at</td><td><?php echo h($validation_result['ticket']['validated_at']); ?></td></tr>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php elseif (isset($validation_result['already_validated']) && $validation_result['already_validated']): ?>
                    <div style="background:#FAEEDA;border:0.5px solid #BA7517;color:#633806;padding:1rem;border-radius:6px;margin-top:16px">
                        <div style="font-weight:500;margin-bottom:6px">⚠ Already Validated</div>
                        <div>
                            <p><?php echo h($validation_result['message']); ?></p>
                            <?php if (isset($validation_result['ticket'])): ?>
                                <table style="font-size:13px;width:100%;margin-top:10px">
                                    <tr><td style="padding:2px 0">Attendee</td><td><strong><?php echo h($validation_result['ticket']['attendee_name']); ?></strong></td></tr>
                                    <tr><td style="padding:2px 0">Event</td><td><?php echo h($validation_result['ticket']['event_name']); ?></td></tr>
                                    <tr><td style="padding:2px 0">Validated at</td><td><?php echo h($validation_result['validated_at']); ?></td></tr>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="background:#FCEBEB;border:0.5px solid #E24B4A;color:#791F1F;padding:1rem;border-radius:6px;margin-top:16px">
                        <div style="font-weight:500;margin-bottom:6px">✗ Validation Failed</div>
                        <div><?php echo h($validation_result['message']); ?></div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <div style="font-size:13px;font-weight:500;margin-bottom:12px">Recent Tickets (Quick Test)</div>
            <?php if ($recent && mysqli_num_rows($recent) > 0): ?>
                <?php while ($rec = mysqli_fetch_assoc($recent)): ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:0.5px solid var(--color-border-tertiary)">
                        <div>
                            <span style="font-size:13px;font-weight:500"><?php echo h($rec['first_name'] . ' ' . $rec['last_name']); ?></span>
                            <br><code style="font-size:10px;color:var(--color-text-secondary)"><?php echo h($rec['ticket_code']); ?></code>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center">
                            <span class="badge <?php echo $rec['is_validated'] ? 'b-green' : 'b-gray'; ?>">
                                <?php echo $rec['is_validated'] ? 'Validated' : 'Pending'; ?>
                            </span>
                            <?php if (!$rec['is_validated']): ?>
                                <a href="?code=<?php echo urlencode($rec['ticket_code']); ?>" class="btn btn-sm">Test</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color:var(--color-text-secondary);font-size:13px">No tickets found.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Camera Scanner Modal -->
<div id="camera-container">
    <div id="camera-box">
        <div id="camera-header">
            <h3>Scan QR Code</h3>
            <button id="camera-close" onclick="closeCameraScanner()">&times;</button>
        </div>
        <div id="qr-reader"></div>
        <div id="camera-error"></div>
        <div id="camera-loading">Initializing camera...</div>
    </div>
</div>

<!-- HTML5 QR Code Library -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<!-- PDF.js Library for PDF processing -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    // Set up PDF.js worker
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
</script>

<script>
let html5QrCode = null;
let cameraActive = false;
let currentCameraIndex = 0;
let availableCameras = [];

function openCameraScanner() {
    const container = document.getElementById('camera-container');
    const loading = document.getElementById('camera-loading');
    const errorDiv = document.getElementById('camera-error');
    
    container.style.display = 'flex';
    loading.style.display = 'block';
    errorDiv.style.display = 'none';
    
    // Clean up any existing instance
    if (html5QrCode) {
        if (cameraActive) {
            html5QrCode.stop().catch(() => {});
        }
        html5QrCode.clear();
    }
    
    html5QrCode = new Html5Qrcode("qr-reader");
    
    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0,
        videoConstraints: {
            width: { ideal: 1280 },
            height: { ideal: 720 }
        }
    };
    
    // Get available cameras
    Html5Qrcode.getCameras().then(devices => {
        if (devices && devices.length) {
            availableCameras = devices;
            currentCameraIndex = 0;
            
            // Find back camera if available (prefer environment over user)
            for (let i = 0; i < devices.length; i++) {
                if (devices[i].label.toLowerCase().includes('back') || 
                    devices[i].label.toLowerCase().includes('environment')) {
                    currentCameraIndex = i;
                    break;
                }
            }
            
            startCameraWithIndex(currentCameraIndex, config, loading, errorDiv);
        } else {
            loading.style.display = 'none';
            errorDiv.style.display = 'block';
            errorDiv.innerHTML = 'No cameras found on this device.<br><br>';
        }
    }).catch(err => {
        loading.style.display = 'none';
        errorDiv.style.display = 'block';
        errorDiv.innerHTML = 'Error accessing camera: ' + err.message + '<br><br>';
    });
}

function startCameraWithIndex(index, config, loading, errorDiv) {
    const cameraId = availableCameras[index].id;
    
    html5QrCode.start(
        cameraId,
        config,
        onScanSuccess,
        onScanFailure
    ).then(() => {
        loading.style.display = 'none';
        cameraActive = true;
    }).catch(err => {
        console.error('Camera error:', err);
        
        // Try next camera if available
        if (index + 1 < availableCameras.length) {
            currentCameraIndex++;
            startCameraWithIndex(currentCameraIndex, config, loading, errorDiv);
        } else {
            loading.style.display = 'none';
            errorDiv.style.display = 'block';
            
            // Provide helpful error message for Brave
            let errorMsg = 'Could not start video source. ';
            if (err.message && err.message.includes('NotReadableError')) {
                errorMsg += '<br><br><strong>Brave Browser Fix:</strong><br>';
                errorMsg += '1. Click the shield icon in address bar<br>';
                errorMsg += '2. Turn OFF "Shields Up" for this site<br>';
                errorMsg += '3. Refresh the page<br><br>';
                errorMsg += 'Alternatively, try:<br>';
                errorMsg += '- Using a different camera<br>';
                errorMsg += '- Ensuring no other app is using the camera';
            } else {
                errorMsg += err.message;
            }
            errorDiv.innerHTML = errorMsg;
        }
    });
}

function closeCameraScanner() {
    const container = document.getElementById('camera-container');
    container.style.display = 'none';
    
    if (html5QrCode && cameraActive) {
        html5QrCode.stop().then(() => {
            cameraActive = false;
            html5QrCode.clear();
        }).catch(err => {
            console.error('Error stopping camera:', err);
        });
    }
}

function onScanSuccess(decodedText, decodedResult) {
    if (decodedText && decodedText.trim() !== '') {
        closeCameraScanner();
        document.getElementById('ticket-code-input').value = decodedText.trim();
        window.location.href = '?code=' + encodeURIComponent(decodedText.trim());
    }
}

function onScanFailure(error) {
    // Silent fail - scanning continues
}

// Close on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCameraScanner();
    }
});

// Close on overlay click
document.getElementById('camera-container').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCameraScanner();
    }
});

// Upload QR Code functions
function openQRUpload() {
    document.getElementById('qr-file-input').click();
}

function handleQRFileUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    console.log('File selected:', file.name, 'Type:', file.type, 'Size:', file.size);

    const fileName = file.name.toLowerCase();
    const isImage = file.type.startsWith('image/') || /\.(jpg|jpeg|png|gif|bmp|webp)$/.test(fileName);
    const isPDF = file.type === 'application/pdf' || fileName.endsWith('.pdf');
    const isHTML = file.type === 'text/html' || fileName.endsWith('.html');

    console.log('Detected - Image:', isImage, 'PDF:', isPDF, 'HTML:', isHTML);

    if (!isImage && !isPDF && !isHTML) {
        alert('Please select a valid image, PDF, or HTML file');
        return;
    }

    if (isImage) {
        // ✅ Pass File object directly — no FileReader needed
        console.log('Processing as image...');
        decodeQRFromImage(file);
    } else {
        // PDF and HTML still need ArrayBuffer
        const reader = new FileReader();
        reader.onload = function(e) {
            if (isPDF) {
                console.log('Processing as PDF...');
                decodePDFQRCode(e.target.result);
            } else {
                console.log('Processing as HTML...');
                decodeHTMLQRCode(e.target.result);
            }
        };
        reader.readAsArrayBuffer(file);
    }

    event.target.value = '';
}

async function decodeQRFromImage(file) {
    const img = new Image();
    const url = URL.createObjectURL(file);
    img.onload = function() {
        const canvas = document.createElement('canvas');
        canvas.width = img.width;
        canvas.height = img.height;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const code = jsQR(imageData.data, canvas.width, canvas.height);
        URL.revokeObjectURL(url);
        if (code) {
            console.log('QR decoded:', code.data);
            document.getElementById('ticket-code-input').value = code.data.trim();
            window.location.href = '?code=' + encodeURIComponent(code.data.trim());
        } else {
            alert('Could not decode QR code. Try a clearer image with more white space around the QR.');
        }
    };
    img.src = url;
}

async function decodePDFQRCode(arrayBuffer) {
    if (typeof pdfjsLib === 'undefined') {
        alert('PDF processing library not loaded. Please try uploading an image instead.');
        return;
    }

    const btn = document.getElementById('upload-qr-btn');
    const originalValue = btn.innerHTML;
    btn.innerHTML = '<span style="font-size:14px">⏳ Processing...</span>';
    btn.disabled = true;

    // Helper: convert a data URL to a File object (fixes Html5Qrcode.scanFile requirement)
    async function dataURLtoFile(dataURL, filename, mimeType) {
        const res = await fetch(dataURL);
        const blob = await res.blob();
        return new File([blob], filename, { type: mimeType });
    }

    // Helper: try scanning a canvas with all three strategies (original PNG, enhanced PNG, JPEG)
    function tryScansOnCanvas(canvas) {
        const ctx = canvas.getContext('2d');
    
        // Strategy 1: scan original
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        const result = jsQR(imageData.data, canvas.width, canvas.height);
        if (result) {
            console.log('✅ QR found (original):', result.data);
            return result.data.trim();
        }

        // Strategy 2: binarized + sharpened
        const enhanced = enhanceImageForQRScanning(canvas);
        const enhCtx = enhanced.getContext('2d');
        const enhData = enhCtx.getImageData(0, 0, enhanced.width, enhanced.height);
        const result2 = jsQR(enhData.data, enhanced.width, enhanced.height);
        if (result2) {
            console.log('✅ QR found (enhanced):', result2.data);
            return result2.data.trim();
        }

        return null;
    }

    function redirect(code) {
        document.getElementById('ticket-code-input').value = code;
        window.location.href = '?code=' + encodeURIComponent(code);
    }

    try {
        const typedArray = new Uint8Array(arrayBuffer);
        console.log('Loading PDF...');
        const pdfDoc = await pdfjsLib.getDocument(typedArray).promise;
        console.log('PDF loaded, pages:', pdfDoc.numPages);

        // ── Pass 1: extract UUID from PDF text layer (fastest, works on your tickets) ──
        console.log('Attempting text extraction...');
        for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
            try {
                const page = await pdfDoc.getPage(pageNum);
                const textContent = await page.getTextContent();
                const text = textContent.items.map(item => item.str).join(' ');
                console.log(`Page ${pageNum} text length:`, text.length);

                const uuidMatch = text.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i);
                if (uuidMatch) {
                    console.log('✅ UUID found in text layer:', uuidMatch[0]);
                    redirect(uuidMatch[0]);
                    return;
                }
            } catch (e) {
                console.log('Text extraction error on page', pageNum, e.message);
            }
        }

        // ── Pass 2: render each page at multiple scales and scan the QR image ──
        console.log('Text extraction found nothing — falling back to QR image scan...');
        for (let pageNum = 1; pageNum <= pdfDoc.numPages; pageNum++) {
            try {
                const page = await pdfDoc.getPage(pageNum);

                for (let scale = 3; scale <= 10; scale++) {
                    try {
                        const canvas = document.createElement('canvas');
                        const viewport = page.getViewport({ scale });
                        canvas.width = viewport.width;
                        canvas.height = viewport.height;

                        await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                        console.log(`Rendered page ${pageNum} at scale ${scale}: ${canvas.width}×${canvas.height}`);

                        const result = tryScansOnCanvas(canvas);
                        if (result) {
                            redirect(result);
                            return;
                        }
                    } catch (renderError) {
                        console.log(`Render error (page ${pageNum}, scale ${scale}):`, renderError.message);
                    }
                }
            } catch (pageError) {
                console.log('Page error:', pageError.message);
            }
        }

        // Nothing worked
        btn.innerHTML = originalValue;
        btn.disabled = false;
        alert('Could not read QR code from PDF.\n\nTips:\n• Try uploading a screenshot/image of the ticket instead\n• Or manually type the ticket code shown below the QR\n• Make sure the PDF is not password-protected');

    } catch (error) {
        btn.innerHTML = originalValue;
        btn.disabled = false;
        console.error('decodePDFQRCode error:', error);
        alert('Error processing PDF: ' + error.message);
    }
}

function decodeHTMLQRCode(arrayBuffer) {
    try {
        // Convert array buffer to string
        const decoder = new TextDecoder();
        const htmlContent = decoder.decode(arrayBuffer);
        
        console.log('HTML file loaded, extracting ticket code...');
        
        // Look for UUID pattern in HTML (8-4-4-4-12 hex format)
        const uuidMatch = htmlContent.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i);
        
        if (uuidMatch) {
            const ticketCode = uuidMatch[0];
            console.log('UUID found in HTML:', ticketCode);
            document.getElementById('ticket-code-input').value = ticketCode.trim();
            window.location.href = '?code=' + encodeURIComponent(ticketCode.trim());
            return;
        }
        
        alert('Could not find ticket code in HTML file. Please ensure it is a valid ticket HTML file.');
    } catch (error) {
        console.error('Error processing HTML:', error);
        alert('Could not process HTML file.\n\nError: ' + error.message);
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>