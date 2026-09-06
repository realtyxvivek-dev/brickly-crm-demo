<div class="mt-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Attachments</h3>
        @if(($task->assigned_to === auth()->id()) || auth()->user()->isAdmin() || auth()->user()->isCrm())
            <button onclick="toggleAttachmentUpload()" class="text-sm text-indigo-600 hover:text-indigo-900 flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Upload File
            </button>
        @endif
    </div>

    <!-- Upload Form -->
    <div id="attachment-upload-form" class="hidden mb-4 bg-gray-50 rounded-lg p-4">
        <form id="attachment-form" enctype="multipart/form-data">
            @csrf
            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-2">Select File</label>
                <input type="file" id="attachment-file" name="file" required
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                       accept="*/*">
                <p class="mt-1 text-xs text-gray-500">Maximum file size: 10MB</p>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="toggleAttachmentUpload()" 
                        class="px-3 py-1 text-sm border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">
                    Upload
                </button>
            </div>
        </form>
    </div>

    <!-- Attachments List -->
    @if(isset($task->attachments) && $task->attachments->count() > 0)
        <div class="space-y-2">
            @foreach($task->attachments as $attachment)
                <div class="flex items-center justify-between bg-gray-50 rounded-lg p-3 hover:bg-gray-100 transition-colors">
                    <div class="flex items-center gap-3 flex-1 min-w-0">
                        @if($attachment->isImage())
                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        @else
                            <svg class="w-5 h-5 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $attachment->file_name }}</p>
                            <p class="text-xs text-gray-500">
                                {{ $attachment->file_size_human }} • Uploaded by {{ $attachment->uploadedBy->name }} • {{ $attachment->created_at->diffForHumans() }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 ml-3">
                        <a href="{{ route('tasks.attachments.download', ['task' => $task, 'attachment' => $attachment]) }}" 
                           class="p-2 text-indigo-600 hover:bg-indigo-50 rounded">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                        </a>
                        @if(($task->assigned_to === auth()->id()) || auth()->user()->isAdmin() || auth()->user()->isCrm())
                            <button onclick="deleteAttachment({{ $attachment->id }})" 
                                    class="p-2 text-red-600 hover:bg-red-50 rounded">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-8 text-gray-500 bg-gray-50 rounded-lg">
            <svg class="mx-auto h-8 w-8 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>
            <p class="text-sm">No attachments yet</p>
        </div>
    @endif
</div>

<script>
function toggleAttachmentUpload() {
    const form = document.getElementById('attachment-upload-form');
    form.classList.toggle('hidden');
}

document.getElementById('attachment-form')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const taskId = {{ $task->id }};
    
    try {
        const response = await axios.post(`/tasks/${taskId}/attachments`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });
        
        if (response.data.success) {
            alert('File uploaded successfully!');
            window.location.reload();
        } else {
            alert('Failed to upload file: ' + response.data.message);
        }
    } catch (error) {
        console.error('Error uploading file:', error);
        if (error.response?.data?.errors) {
            const errors = Object.values(error.response.data.errors).flat().join('\n');
            alert('Validation errors:\n' + errors);
        } else {
            alert('Error: ' + (error.response?.data?.message || 'Failed to upload file'));
        }
    }
});

function deleteAttachment(attachmentId) {
    if (!confirm('Are you sure you want to delete this attachment?')) {
        return;
    }
    
    const taskId = {{ $task->id }};
    
    axios.delete(`/tasks/${taskId}/attachments/${attachmentId}`)
        .then(response => {
            if (response.data.success) {
                alert('Attachment deleted successfully!');
                window.location.reload();
            } else {
                alert('Failed to delete: ' + response.data.message);
            }
        })
        .catch(error => {
            console.error('Error deleting attachment:', error);
            alert('Error: ' + (error.response?.data?.message || 'Failed to delete attachment'));
        });
}
</script>
