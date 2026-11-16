<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>معاينة: {{ $homeSection->title }}</title>

    {{-- Tailwind CSS & FontAwesome --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&family=Cairo:wght@400;600;700;900&family=Alexandria:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />

    <style>
        body {
            font-family: 'Tajawal', 'Cairo', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }

        /* Essential Website Brand Helpers */
        .gradient-primary {
            background: linear-gradient(135deg, #145147 0%, #37a05c 50%, #2d8a4e 100%);
        }
        .gradient-secondary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        .glass-effect {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        /* Dynamic Rich Text Styles */
        .dynamic-rich-text h1, .dynamic-rich-text h2, .dynamic-rich-text h3 {
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #0f172a;
        }
        .dynamic-rich-text p {
            margin-bottom: 0.75rem;
            line-height: 1.8;
        }
        .dynamic-rich-text img {
            max-width: 100%;
            height: auto;
            border-radius: 0.75rem;
        }
        .dynamic-rich-text table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        .dynamic-rich-text table th, .dynamic-rich-text table td {
            border: 1px solid #cbd5e1;
            padding: 0.5rem 0.75rem;
        }
        .dynamic-rich-text table th {
            background-color: #f1f5f9;
        }

        /* Dynamic HTML Content */
        .dynamic-html-content {
            width: 100%;
        }

        /* Responsive Container */
        .preview-wrapper {
            width: 100%;
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div class="preview-wrapper">
        @include('partials.home.dynamic-sections', ['dynamicSections' => collect([$homeSection])])
    </div>

    {{-- Swiper Bundle for Carousels --}}
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // Init any swipers on page
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Swiper !== 'undefined') {
                document.querySelectorAll('.swiper-container, .swiper').forEach(function(el) {
                    new Swiper(el, {
                        slidesPerView: 1,
                        spaceBetween: 20,
                        loop: true,
                        autoplay: { delay: 3500 },
                        pagination: { el: '.swiper-pagination', clickable: true },
                        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
                    });
                });
            }
        });
    </script>
</body>
</html>
