@extends('layouts.app')

@section('title', 'Create Support Ticket')
@section('page-title', 'Support')
@section('page-subtitle', 'Submit a new support request')

@section('content')
<div style="padding:24px;max-width:860px;">

    <div style="margin-bottom:20px;">
        <a href="{{ route('support.index') }}" style="color:#64748b;text-decoration:none;font-size:14px;display:inline-flex;align-items:center;gap:6px;">
            <i class="fas fa-arrow-left"></i> Back to My Tickets
        </a>
    </div>

    @if($errors->any())
    <div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
        <ul style="margin:0;padding-left:20px;">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('support.store') }}" enctype="multipart/form-data" id="ticketForm">
        @csrf

        {{-- Main card --}}
        <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:28px;margin-bottom:20px;">
            <h3 style="margin:0 0 20px;font-size:16px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-info-circle" style="color:#3b82f6;"></i> Ticket Details
            </h3>

            {{-- Title --}}
            <div style="margin-bottom:18px;">
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Title <span style="color:#ef4444;">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required
                    style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;color:#1e293b;outline:none;box-sizing:border-box;transition:border-color 0.2s;"
                    placeholder="Brief summary of your issue"
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e2e8f0'">
            </div>

            {{-- Category + Priority row --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Category <span style="color:#ef4444;">*</span></label>
                    <select name="category" required style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;color:#1e293b;outline:none;background:#fff;">
                        <option value="">Select category</option>
                        <option value="bug"             {{ old('category')==='bug' ? 'selected' : '' }}>Bug / Error</option>
                        <option value="feature_request" {{ old('category')==='feature_request' ? 'selected' : '' }}>Feature Request</option>
                        <option value="question"        {{ old('category')==='question' ? 'selected' : '' }}>Question</option>
                        <option value="account"         {{ old('category')==='account' ? 'selected' : '' }}>Account Issue</option>
                        <option value="other"           {{ old('category')==='other' ? 'selected' : '' }}>Other</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Priority <span style="color:#ef4444;">*</span></label>
                    <select name="priority" required style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;color:#1e293b;outline:none;background:#fff;">
                        <option value="low"    {{ old('priority')==='low' ? 'selected' : '' }}>Low</option>
                        <option value="medium" {{ old('priority', 'medium')==='medium' ? 'selected' : '' }}>Medium</option>
                        <option value="high"   {{ old('priority')==='high' ? 'selected' : '' }}>High</option>
                        <option value="urgent" {{ old('priority')==='urgent' ? 'selected' : '' }}>Urgent</option>
                    </select>
                </div>
            </div>

            {{-- Description --}}
            <div>
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:6px;">Description <span style="color:#ef4444;">*</span></label>
                <textarea name="description" required rows="6"
                    style="width:100%;padding:10px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;color:#1e293b;outline:none;resize:vertical;box-sizing:border-box;transition:border-color 0.2s;"
                    placeholder="Describe your issue in detail — what happened, what you expected, steps to reproduce..."
                    onfocus="this.style.borderColor='#3b82f6'" onblur="this.style.borderColor='#e2e8f0'">{{ old('description') }}</textarea>
            </div>
        </div>

        {{-- Attachments card --}}
        <div style="background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:28px;margin-bottom:20px;">
            <h3 style="margin:0 0 20px;font-size:16px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-paperclip" style="color:#3b82f6;"></i> Attachments <span style="font-size:13px;font-weight:400;color:#94a3b8;">(optional)</span>
            </h3>

            {{-- File Upload --}}
            <div style="margin-bottom:24px;">
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:10px;">
                    <i class="fas fa-file" style="color:#6366f1;margin-right:6px;"></i> Files & Images
                </label>
                <div id="dropZone" style="border:2px dashed #cbd5e1;border-radius:10px;padding:28px;text-align:center;cursor:pointer;transition:border-color 0.2s;"
                    onclick="document.getElementById('fileInput').click()"
                    ondragover="event.preventDefault();this.style.borderColor='#3b82f6'"
                    ondragleave="this.style.borderColor='#cbd5e1'"
                    ondrop="handleDrop(event)">
                    <i class="fas fa-cloud-upload-alt" style="font-size:32px;color:#94a3b8;margin-bottom:10px;display:block;"></i>
                    <p style="margin:0;color:#64748b;font-size:14px;">Drag & drop files here or <span style="color:#3b82f6;font-weight:600;">browse</span></p>
                    <p style="margin:6px 0 0;color:#94a3b8;font-size:12px;">Images, PDFs, documents — max 10MB each, up to 5 files</p>
                </div>
                <input type="file" id="fileInput" name="attachments[]" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt" style="display:none;" onchange="showFilePreview(this)">
                <div id="filePreview" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;"></div>
            </div>

            {{-- Voice Recorder --}}
            <div style="margin-bottom:24px;">
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:10px;">
                    <i class="fas fa-microphone" style="color:#ef4444;margin-right:6px;"></i> Voice Recording
                </label>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <button type="button" id="voiceBtn" onclick="toggleVoice()"
                        style="background:#fee2e2;color:#ef4444;border:1.5px solid #fca5a5;padding:10px 18px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-microphone" id="voiceIcon"></i>
                        <span id="voiceLabel">Start Recording</span>
                    </button>
                    <span id="voiceTimer" style="font-size:14px;color:#ef4444;font-weight:600;display:none;font-family:monospace;">00:00</span>
                    <span id="voiceStatus" style="font-size:13px;color:#94a3b8;"></span>
                </div>
                <div id="voicePreview" style="margin-top:12px;display:none;">
                    <audio id="voicePlayer" controls style="width:100%;max-width:380px;margin-top:6px;border-radius:8px;"></audio>
                    <button type="button" onclick="clearVoice()" style="background:none;border:none;color:#ef4444;font-size:12px;cursor:pointer;margin-top:6px;display:block;">
                        <i class="fas fa-trash" style="margin-right:4px;"></i> Remove recording
                    </button>
                </div>
                <input type="hidden" name="voice_data" id="voiceData">
            </div>

            {{-- Video Recorder --}}
            <div>
                <label style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:10px;">
                    <i class="fas fa-video" style="color:#8b5cf6;margin-right:6px;"></i> Video Recording
                </label>
                <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                    <button type="button" id="videoBtn" onclick="toggleVideo()"
                        style="background:#ede9fe;color:#7c3aed;border:1.5px solid #c4b5fd;padding:10px 18px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;">
                        <i class="fas fa-video" id="videoIcon"></i>
                        <span id="videoLabel">Record Video</span>
                    </button>
                    <span id="videoTimer" style="font-size:14px;color:#7c3aed;font-weight:600;display:none;font-family:monospace;">00:00</span>
                    <span id="videoStatus" style="font-size:13px;color:#94a3b8;"></span>
                </div>
                <div id="videoLive" style="margin-top:12px;display:none;">
                    <video id="videoLiveStream" autoplay muted playsinline style="width:100%;max-width:420px;border-radius:10px;border:2px solid #c4b5fd;background:#000;"></video>
                </div>
                <div id="videoPreview" style="margin-top:12px;display:none;">
                    <video id="videoPlayer" controls style="width:100%;max-width:420px;border-radius:10px;border:2px solid #e2e8f0;background:#000;"></video>
                    <button type="button" onclick="clearVideo()" style="background:none;border:none;color:#ef4444;font-size:12px;cursor:pointer;margin-top:6px;display:block;">
                        <i class="fas fa-trash" style="margin-right:4px;"></i> Remove video
                    </button>
                </div>
                <input type="hidden" name="video_data" id="videoData">
            </div>
        </div>

        {{-- Submit --}}
        <div style="display:flex;gap:12px;align-items:center;">
            <button type="submit" id="submitBtn"
                style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;padding:12px 28px;border:none;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:8px;">
                <i class="fas fa-paper-plane"></i> Submit Ticket
            </button>
            <a href="{{ route('support.index') }}" style="color:#64748b;text-decoration:none;font-size:14px;">Cancel</a>
        </div>
    </form>
</div>

<script>
// ─── File Upload ─────────────────────────────────────────────────────────────
function handleDrop(e) {
    e.preventDefault();
    document.getElementById('dropZone').style.borderColor = '#cbd5e1';
    const dt = new DataTransfer();
    const existing = document.getElementById('fileInput').files;
    for (let f of existing) dt.items.add(f);
    for (let f of e.dataTransfer.files) dt.items.add(f);
    document.getElementById('fileInput').files = dt.files;
    showFilePreview(document.getElementById('fileInput'));
}

function showFilePreview(input) {
    const preview = document.getElementById('filePreview');
    preview.innerHTML = '';
    for (let file of input.files) {
        const el = document.createElement('div');
        el.style.cssText = 'background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;display:flex;align-items:center;gap:8px;font-size:13px;color:#475569;';
        el.innerHTML = '<i class="fas fa-file" style="color:#6366f1;"></i><span>' + file.name + '</span>';
        preview.appendChild(el);
    }
}

// ─── Voice Recording ──────────────────────────────────────────────────────────
let voiceRecorder, voiceChunks = [], voiceStream, voiceInterval, voiceSecs = 0, voiceRecording = false;

async function toggleVoice() {
    if (!voiceRecording) {
        try {
            voiceStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            voiceChunks = [];
            voiceRecorder = new MediaRecorder(voiceStream);
            voiceRecorder.ondataavailable = e => voiceChunks.push(e.data);
            voiceRecorder.onstop = () => {
                const blob = new Blob(voiceChunks, { type: 'audio/webm' });
                const reader = new FileReader();
                reader.onload = () => {
                    document.getElementById('voiceData').value = reader.result;
                    document.getElementById('voicePlayer').src = reader.result;
                    document.getElementById('voicePreview').style.display = 'block';
                    document.getElementById('voiceStatus').textContent = 'Recording saved';
                };
                reader.readAsDataURL(blob);
                voiceStream.getTracks().forEach(t => t.stop());
            };
            voiceRecorder.start();
            voiceRecording = true;
            voiceSecs = 0;
            document.getElementById('voiceTimer').style.display = 'inline';
            document.getElementById('voiceLabel').textContent = 'Stop Recording';
            document.getElementById('voiceIcon').className = 'fas fa-stop';
            document.getElementById('voiceBtn').style.background = '#dc2626';
            document.getElementById('voiceBtn').style.color = '#fff';
            voiceInterval = setInterval(() => {
                voiceSecs++;
                const m = String(Math.floor(voiceSecs/60)).padStart(2,'0');
                const s = String(voiceSecs%60).padStart(2,'0');
                document.getElementById('voiceTimer').textContent = m+':'+s;
            }, 1000);
        } catch(e) {
            document.getElementById('voiceStatus').textContent = 'Microphone access denied';
        }
    } else {
        voiceRecorder.stop();
        voiceRecording = false;
        clearInterval(voiceInterval);
        document.getElementById('voiceTimer').style.display = 'none';
        document.getElementById('voiceLabel').textContent = 'Start Recording';
        document.getElementById('voiceIcon').className = 'fas fa-microphone';
        document.getElementById('voiceBtn').style.background = '#fee2e2';
        document.getElementById('voiceBtn').style.color = '#ef4444';
    }
}

function clearVoice() {
    document.getElementById('voiceData').value = '';
    document.getElementById('voicePlayer').src = '';
    document.getElementById('voicePreview').style.display = 'none';
    document.getElementById('voiceStatus').textContent = '';
}

// ─── Video Recording ──────────────────────────────────────────────────────────
let videoRecorder, videoChunks = [], videoStream, videoInterval, videoSecs = 0, videoRecording = false;

async function toggleVideo() {
    if (!videoRecording) {
        try {
            videoStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
            videoChunks = [];
            videoRecorder = new MediaRecorder(videoStream);
            videoRecorder.ondataavailable = e => videoChunks.push(e.data);
            videoRecorder.onstop = () => {
                const blob = new Blob(videoChunks, { type: 'video/webm' });
                const reader = new FileReader();
                reader.onload = () => {
                    document.getElementById('videoData').value = reader.result;
                    document.getElementById('videoPlayer').src = reader.result;
                    document.getElementById('videoLive').style.display = 'none';
                    document.getElementById('videoPreview').style.display = 'block';
                    document.getElementById('videoStatus').textContent = 'Video saved';
                };
                reader.readAsDataURL(blob);
                videoStream.getTracks().forEach(t => t.stop());
            };
            const liveEl = document.getElementById('videoLiveStream');
            liveEl.srcObject = videoStream;
            document.getElementById('videoLive').style.display = 'block';
            videoRecorder.start();
            videoRecording = true;
            videoSecs = 0;
            document.getElementById('videoTimer').style.display = 'inline';
            document.getElementById('videoLabel').textContent = 'Stop Recording';
            document.getElementById('videoIcon').className = 'fas fa-stop';
            document.getElementById('videoBtn').style.background = '#7c3aed';
            document.getElementById('videoBtn').style.color = '#fff';
            videoInterval = setInterval(() => {
                videoSecs++;
                const m = String(Math.floor(videoSecs/60)).padStart(2,'0');
                const s = String(videoSecs%60).padStart(2,'0');
                document.getElementById('videoTimer').textContent = m+':'+s;
            }, 1000);
        } catch(e) {
            document.getElementById('videoStatus').textContent = 'Camera/microphone access denied';
        }
    } else {
        videoRecorder.stop();
        videoRecording = false;
        clearInterval(videoInterval);
        document.getElementById('videoTimer').style.display = 'none';
        document.getElementById('videoLabel').textContent = 'Record Video';
        document.getElementById('videoIcon').className = 'fas fa-video';
        document.getElementById('videoBtn').style.background = '#ede9fe';
        document.getElementById('videoBtn').style.color = '#7c3aed';
    }
}

function clearVideo() {
    document.getElementById('videoData').value = '';
    document.getElementById('videoPlayer').src = '';
    document.getElementById('videoPreview').style.display = 'none';
    document.getElementById('videoStatus').textContent = '';
}

// Prevent accidental submission while recording
document.getElementById('ticketForm').addEventListener('submit', function(e) {
    if (voiceRecording || videoRecording) {
        e.preventDefault();
        alert('Please stop the recording before submitting.');
    }
});
</script>
@endsection
