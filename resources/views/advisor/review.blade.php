<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave a Review | {{ $profile->user->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f7f6f3]" style="font-family: 'Outfit', sans-serif;">
    <div class="min-h-screen bg-[radial-gradient(circle_at_top,_rgba(32,90,68,0.10),_transparent_36%),linear-gradient(180deg,_#f7f6f3_0%,_#ffffff_100%)]">
        <div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="overflow-hidden rounded-[32px] border border-white/70 bg-white shadow-[0_24px_80px_rgba(6,58,28,0.12)]">
                <div class="bg-gradient-to-r from-[#063A1C] to-[#205A44] px-6 py-8 text-white sm:px-8">
                    <div class="flex items-center gap-4">
                        <img
                            src="{{ $profile->user->profile_picture_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($profile->user->name) . '&background=ffffff&color=063A1C&size=200' }}"
                            alt="{{ $profile->user->name }}"
                            class="h-16 w-16 rounded-2xl border border-white/30 object-cover"
                        >
                        <div>
                            <h1 class="text-2xl font-bold">Leave a Review</h1>
                            <p class="mt-1 text-sm text-emerald-50/90">for {{ $profile->user->name }}{{ $profile->designation ? ' • ' . $profile->designation : '' }}</p>
                        </div>
                    </div>
                </div>

                <div class="p-6 sm:p-8">
                    <div class="mb-6 rounded-2xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                        Aapka mobile number sirf verification ke liye use hoga. Ye public page par show nahi hoga.
                    </div>

                    <form action="{{ route('advisor.public.review.store', $profile->public_slug) }}" method="POST" class="space-y-5">
                        @csrf
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Your Name</label>
                                <input type="text" name="customer_name" value="{{ old('customer_name') }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="Enter your full name" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Mobile Number</label>
                                <input type="text" name="mobile_number" value="{{ old('mobile_number') }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="Enter your mobile number" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
                                <select name="rating" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" required>
                                    <option value="">Select Rating</option>
                                    @for($rating = 5; $rating >= 1; $rating--)
                                        <option value="{{ $rating }}" {{ (string) old('rating') === (string) $rating ? 'selected' : '' }}>{{ $rating }} / 5</option>
                                    @endfor
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Project Name (Optional)</label>
                                <input type="text" name="project_name" value="{{ old('project_name') }}" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="Project name">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Your Review</label>
                            <textarea name="review_text" rows="5" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm" placeholder="Aapka experience kaisa raha?" required>{{ old('review_text') }}</textarea>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                            <a href="{{ route('advisor.public.show', $profile->public_slug) }}" class="text-sm font-semibold text-[#205A44]">Back to profile</a>
                            <button type="submit" class="inline-flex items-center rounded-2xl bg-[#063A1C] px-6 py-3 text-sm font-semibold text-white hover:bg-[#205A44]">
                                Submit Review
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
