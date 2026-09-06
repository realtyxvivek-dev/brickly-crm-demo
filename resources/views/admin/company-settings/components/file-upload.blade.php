@php
    $fileType = $fileType ?? 'logo';
    $currentFile = $currentFile ?? null;
    $accept = $accept ?? 'image/*';
    $maxSize = $maxSize ?? '2MB';
    $dimensions = $dimensions ?? '';
@endphp

<div class="file-upload-container" data-file-type="{{ $fileType }}">
    <div class="border-2 border-dashed border-[#E5DED4] rounded-lg p-6 text-center hover:border-brand transition-colors">
        @if($currentFile)
            <div class="mb-4">
                <img src="{{ asset('storage/' . $currentFile->file_path) }}" alt="{{ $fileType }}" 
                     class="max-w-full h-32 mx-auto object-contain rounded-lg border border-[#E5DED4]">
                <p class="text-xs text-[#B3B5B4] mt-2">{{ $currentFile->file_name }}</p>
                <button type="button" onclick="deleteFile({{ $currentFile->id }}, '{{ $fileType }}')" 
                        class="mt-2 text-red-600 hover:text-red-800 text-sm">
                    <i class="fas fa-trash mr-1"></i> Remove
                </button>
            </div>
        @endif
        
        <input type="file" 
               id="file-{{ $fileType }}" 
               name="file_{{ $fileType }}"
               accept="{{ $accept }}"
               class="hidden"
               onchange="handleFileUpload(this, '{{ $fileType }}')">
        
        <label for="file-{{ $fileType }}" class="cursor-pointer">
            <div class="flex flex-col items-center">
                <i class="fas fa-cloud-upload-alt text-4xl text-[#B3B5B4] mb-2"></i>
                <p class="text-sm text-brand-primary font-medium">
                    {{ $currentFile ? 'Replace' : 'Upload' }} {{ ucfirst(str_replace('_', ' ', $fileType)) }}
                </p>
                <p class="text-xs text-[#B3B5B4] mt-1">
                    Max size: {{ $maxSize }}
                    @if($dimensions)
                        <br>{{ $dimensions }}
                    @endif
                </p>
            </div>
        </label>
    </div>
    
    <div id="upload-progress-{{ $fileType }}" class="mt-2 hidden">
        <div class="w-full bg-gray-200 rounded-full h-2">
            <div class="h-2 rounded-full transition-all duration-300" style="width: 0%; background: var(--primary-color);"></div>
        </div>
        <p class="text-xs text-[#B3B5B4] mt-1 text-center">Uploading...</p>
    </div>
</div>
