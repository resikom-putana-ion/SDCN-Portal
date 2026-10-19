(() => {
    const translations = {
        'Beranda':'Home', 'Tentang Kami':'About Us', 'Program':'Programs', 'Fasilitas':'Facilities',
        'Guru & Staf':'Teachers & Staff', 'Galeri':'Gallery', 'Kontak':'Contact', 'Pengumuman':'Announcements',
        'Daftar Sekarang':'Register Now', 'Lihat Profil Sekolah':'View School Profile', 'Lewati ke konten':'Skip to content',
        'Tanya via WhatsApp':'Ask us on WhatsApp', 'SIAP BERKENALAN DENGAN KAMI?':'READY TO MEET US?',
        'Mari mulai langkah pertama bersama.':'Let us take the first step together.',
        'Siap mengenal SD Ceria Nusantara lebih dekat?':'Ready to learn more about SD Ceria Nusantara?',
        'Daftar secara online atau jadwalkan kunjungan ke sekolah.':'Register online or schedule a school visit.',
        'Hubungi kami atau mulai proses pendaftaran online.':'Contact us or start the online registration process.',
        'Jadwalkan Kunjungan':'Schedule a Visit', 'Privasi':'Privacy', 'Syarat & Ketentuan':'Terms & Conditions',
        'Hak cipta dilindungi.':'All rights reserved.', 'PENDAFTARAN SISWA BARU':'NEW STUDENT REGISTRATION',
        'Awal perjalanan':'The beginning of', 'ceria si kecil!':'your child’s bright journey!',
        'Isi data dengan santai. Kami pandu sampai selesai.':'Fill in the details at your own pace. We’ll guide you through every step.',
        'Data Anak':'Child Information', 'Data Orang Tua':'Parent Information', 'Unggah Berkas':'Upload Documents',
        'Periksa & Kirim':'Review & Submit', 'Kenalan dulu dengan calon bintang kecil yang akan belajar dan tumbuh bersama kami.':'Tell us about your little star who will learn and grow with us.',
        'Bagikan kontak orang tua atau wali agar tim sekolah mudah menghubungi.':'Share a parent or guardian’s contact details so the school can reach you.',
        'Unggah berkas yang jelas untuk membantu proses pemeriksaan pendaftaran.':'Upload clear documents to help us review the application.',
        'Periksa sekali lagi. Pastikan semua informasi sudah sesuai sebelum dikirim.':'Review everything once more before submitting.',
        'Kenalan dengan si kecil':'Meet your little one', 'Siapa nama calon murid kita?':'What is the student’s name?',
        'Nama lengkap anak':'Child’s full name', 'Nama panggilan':'Preferred name', 'Tanggal lahir':'Date of birth',
        'Jenis kelamin':'Gender', 'Pilih salah satu':'Choose one', 'Laki-laki':'Boy', 'Perempuan':'Girl',
        'Asal sekolah / PAUD':'Previous school / preschool', 'Opsional':'Optional',
        'Kebutuhan pendampingan':'Additional support needs', 'Orang tua atau wali':'Parent or guardian',
        'Nama orang tua / wali':'Parent / guardian name', 'Hubungan dengan anak':'Relationship to child',
        'Pilih hubungan':'Choose relationship', 'Ayah':'Father', 'Ibu':'Mother', 'Wali':'Guardian',
        'Nomor WhatsApp':'WhatsApp number', 'Alamat email':'Email address', 'Alamat domisili':'Home address',
        'Kontak darurat':'Emergency contact', 'Info tambahan':'Additional information',
        'Jarak domisili ke sekolah':'Distance from home to school', 'Hitung dari lokasi saya':'Calculate from my location',
        'Akta kelahiran':'Birth certificate', 'Kartu Keluarga':'Family card', 'Pas foto anak':'Child’s photo',
        'Periksa & Kirim':'Review & Submit', 'Dokumen':'Documents', 'Pembayaran pendaftaran':'Registration payment',
        'Biaya pendaftaran':'Registration fee', 'Bayar langsung di sekolah':'Pay in person at school',
        'Pembayaran dilakukan kepada petugas sekolah.':'Pay the school staff in person.',
        'Transfer bank BCA':'BCA bank transfer', 'Bukti pembayaran transfer':'Transfer payment proof',
        'Kirim Pendaftaran 🎉':'Submit Registration 🎉', 'Lanjutkan':'Continue', 'Kembali':'Back',
        'Saya menyetujui':'I agree to the', 'kebijakan privasi':'privacy policy',
        'dan pemrosesan data untuk keperluan pendaftaran sekolah.':'and the processing of my data for school registration.',
        'Data pribadi dijaga aman.':'Your personal data is kept secure.', '* Kolom wajib diisi.':'* Required fields.',
        'Pendaftaran berhasil dikirim':'Registration submitted successfully',
        'Terima kasih. Tim penerimaan akan segera menghubungi Anda.':'Thank you. Our admissions team will contact you soon.',
        'NOMOR PENDAFTARAN':'REGISTRATION NUMBER', 'PEMBAYARAN':'PAYMENT',
        'Pantau hasil pendaftaran':'Track your application', 'Cek Pengumuman':'Check Announcements',
        'Kembali ke Beranda':'Back to Home', 'PENDAFTARAN SISWA BARU':'NEW STUDENT REGISTRATION',
        'Belajar Ceria,':'Happy Learning,', 'Tumbuh Percaya Diri':'Growing with Confidence',
        'TUMBUH CERIA SETIAP HARI':'GROWING HAPPILY EVERY DAY',
        'Ruang belajar yang hangat untuk anak mengeksplorasi, bertanya, dan menemukan potensi terbaiknya.':'A welcoming place for children to explore, ask questions, and discover their potential.',
        'Siswa aktif':'Active students', 'Guru & staf':'Teachers & staff', 'Tahun berdiri':'Established',
        'Akreditasi':'Accreditation', 'Mendampingi setiap anak tumbuh ceria dan percaya diri.':'Helping every child grow with joy and confidence.',
        'PROFIL SEKOLAH':'ABOUT OUR SCHOOL', 'Selamat datang di SD Ceria Nusantara':'Welcome to SD Ceria Nusantara',
        'Tempat anak merasa diterima, berani mencoba, dan senang belajar.':'A place where children feel welcome, try new things, and enjoy learning.',
        'Kenali Sekolah Kami':'Discover Our School', 'KESEHARIAN':'A DAY AT SCHOOL',
        'Belajar dengan rasa ingin tahu':'Learning with curiosity', 'MENGAPA MEMILIH KAMI':'WHY CHOOSE US',
        'MENGAPA KAMI':'WHY US', 'Mendampingi setiap langkah kecil':'Supporting every little step',
        'Keunggulan yang terasa dalam pengalaman belajar sehari-hari.':'What makes everyday learning special.',
        'Pembelajaran menyenangkan':'Joyful learning', 'Perhatian pada tiap anak':'Care for every child',
        'Karakter yang kuat':'Strong character', 'Komunikasi dengan orang tua':'Parent communication',
        'PROGRAM UNGGULAN':'FEATURED PROGRAMS', 'Belajar melalui pengalaman':'Learning through experience',
        'Kegiatan dirancang agar pengetahuan, kreativitas, dan karakter berkembang bersama.':'Activities that nurture knowledge, creativity, and character.',
        'Pelajari program':'Explore the program', 'FASILITAS SEKOLAH':'SCHOOL FACILITIES',
        'Ruang nyaman untuk banyak kemungkinan':'Comfortable spaces for endless possibilities',
        'FASILITAS':'FACILITIES', 'Ruang nyaman untuk tumbuh':'Comfortable spaces to grow',
        'Setiap area mendukung anak belajar, bermain, dan mengeksplorasi.':'Every space supports learning, play, and exploration.',
        'Cerita seru dari':'Stories from', 'setiap hari belajar':'every school day',
        'Ruang dokumentasi kegiatan dan karya anak.':'A collection of school activities and children’s work.',
        'HUBUNGI KAMI':'CONTACT US', 'Ada pertanyaan?':'Have a question?', 'Mari bicara bersama':'Let’s talk',
        'Tim sekolah siap mendengar kebutuhan keluarga.':'Our school team is ready to hear from your family.',
        'PERTANYAAN UMUM':'FREQUENTLY ASKED QUESTIONS', 'Jawaban cepat untuk orang tua':'Quick answers for parents',
        'CERITA KELUARGA':'FAMILY STORIES', 'Kepercayaan tumbuh dari pengalaman':'Trust grows through experience',
        'ALUR PENDAFTARAN':'HOW TO APPLY', 'Empat langkah, lebih mudah diikuti':'Four simple steps to apply',
        'Empat langkah, lebih mudah':'Four simple steps', 'Mulai Pendaftaran':'Start Registration',
        'INFORMASI PENDAFTARAN':'ADMISSION INFORMATION', 'Jelas sebelum mengambil keputusan':'Know before you decide',
        'DAFTAR ONLINE':'REGISTER ONLINE', 'Mulai perjalanan belajar anak':'Start your child’s learning journey',
        'Lengkapi data anak untuk melanjutkan pendaftaran.':'Enter your child’s details to continue registration.',
        'Nama lengkap anak':'Child’s full name', 'Isi nama lengkap anak':'Enter the child’s full name',
        'Isi nama panggilan':'Enter a preferred name', 'Pilih jenis kelamin':'Choose gender',
        'Lanjutkan':'Continue', 'Kepala Sekolah':'Principal', 'SAMBUTAN KEPALA SEKOLAH':'A MESSAGE FROM THE PRINCIPAL',
        'Pendamping tumbuh kembang anak':'Supporting children’s growth', 'Lihat profil guru dan staf':'Meet our teachers and staff',
        'Belajar bersama':'Learning together', 'Ruang belajar yang ceria':'A joyful learning space',
        'Pengumuman':'Announcements', 'Masuk':'Sign in', 'Cari':'Search', 'Kirim':'Submit',
        'Nama':'Name', 'Email':'Email', 'Pesan':'Message', 'Tanggal':'Date', 'Catatan':'Notes',
        'Kirim pesan':'Send message', 'Kirim permintaan':'Send request', 'Jadwalkan kunjungan':'Schedule a visit',
        'Privasi dan data':'Privacy and data', 'Syarat dan ketentuan':'Terms and conditions',
        'Nomor pendaftaran':'Registration number', 'Tanggal lahir anak':'Child’s date of birth',
        'Lihat hasil':'View results', 'Belum ada pengumuman':'No announcements yet',
        'Menunggu informasi lanjutan':'Wait for further updates', 'Pantau pengumuman':'Check announcements',
        'Simpan nomor pendaftaran':'Save your registration number', 'Tunggu informasi lanjutan':'Wait for further updates',
        'PENDAFTARAN SISWA BARU':'NEW STUDENT REGISTRATION',
        'Kawasan SD Ceria Nusantara':'SD Ceria Nusantara campus',
        'Ruang belajar yang ceria':'A joyful learning space',
        'Teman baru di SD Ceria!':'A new friend at SD Ceria!',
        'Belajar aktif':'Active learning', 'Anak berani mencoba hal baru.':'Children are encouraged to try new things.',
        'Lingkungan hangat':'A welcoming environment', 'Rasa aman untuk bertumbuh.':'A safe place to grow.',
        'Berkembang bersama':'Growing together', 'Sekolah dan keluarga sejalan.':'School and family working together.',
        'Metode belajar mendorong rasa ingin tahu dan partisipasi aktif anak.':'Learning methods encourage curiosity and active participation.',
        'Guru membimbing perkembangan akademik serta sosial emosional.':'Teachers support academic and social-emotional development.',
        'Kebiasaan baik dibangun melalui contoh dan kegiatan bersama.':'Good habits grow through example and shared activities.',
        'Perkembangan anak dibagikan melalui komunikasi yang terbuka.':'Children’s progress is shared through open communication.',
        'Fondasi belajar yang menyenangkan.':'A joyful foundation for learning.',
        'Ruang untuk berekspresi dan berkarya.':'Space to express and create.',
        'Gerak aktif dan kerja sama tim.':'Active movement and teamwork.',
        'Perhatian pada tiap anak':'Care for every child',
        'Bimbingan sesuai kebutuhan dan cara belajar setiap anak.':'Guidance tailored to each child’s needs and learning style.',
        'Ruang yang aman':'A safe space', 'Anak bebas bertanya, bereksperimen, dan mencoba lagi.':'Children are free to ask, experiment, and try again.',
        'Bersama keluarga':'Together with families',
        'Komunikasi terbuka untuk mendukung perkembangan anak.':'Open communication to support each child’s development.',
        'Literasi & numerasi':'Literacy & numeracy',
        'Fondasi berpikir melalui cerita, permainan, dan aktivitas bermakna.':'Build thinking skills through stories, games, and meaningful activities.',
        'Seni & kreativitas':'Arts & creativity',
        'Ruang berekspresi, berkarya, dan menumbuhkan percaya diri.':'A space to express, create, and build confidence.',
        'Gerak & kolaborasi':'Movement & collaboration',
        'Tubuh sehat dan kemampuan bekerja sama melalui permainan aktif.':'Healthy bodies and teamwork through active play.',
        '1. Data anak':'1. Child information', 'Isi identitas dan asal sekolah anak.':'Enter the child’s details and previous school.',
        '2. Data orang tua':'2. Parent information', 'Lengkapi kontak orang tua atau wali.':'Add a parent or guardian’s contact details.',
        '3. Unggah berkas':'3. Upload documents', 'Siapkan akta, KK, KIA, dan pas foto.':'Prepare the birth certificate, family card, child ID card, and photo.',
        '4. Periksa & kirim':'4. Review & submit', 'Simpan nomor pendaftaran Anda.':'Save your registration number.',
        'Tempat anak merasa diterima, berani mencoba, dan senang belajar.':'A place where children feel welcome, try new things, and enjoy learning.',
        'Kami percaya masa sekolah dasar adalah awal perjalanan yang penuh rasa ingin tahu.':'We believe primary school is the start of a journey full of curiosity.',
        'Melalui pengalaman belajar yang menyenangkan, setiap anak diajak berkembang':'Through joyful learning experiences, every child is encouraged to grow',
        'sesuai ritmenya dengan dukungan guru dan keluarga.':'at their own pace with support from teachers and family.',
        'Tempat tumbuh':'A place to grow', 'dan menemukan potensi':'and discover potential',
        'Kenali nilai, visi, dan cara kami mendampingi anak.':'Discover our values, vision, and how we support children.',
        'Sekolah yang dekat dengan dunia anak':'A school close to children’s world',
        'Sejak 2014, mendampingi anak dengan hati dan rasa ingin tahu.':'Since 2014, supporting children with care and curiosity.',
        'Kami membangun ruang belajar tempat setiap anak merasa aman untuk bertanya, bereksperimen, dan mencoba lagi.':'We create a learning space where every child feels safe to ask questions, experiment, and try again.',
        'Guru dan keluarga berjalan searah untuk mendukung perkembangan mereka.':'Teachers and families work together to support their growth.',
        'Anak tumbuh percaya diri, peduli, dan gemar belajar.':'Children grow confident, caring, and eager to learn.',
        'Pengalaman belajar aktif dengan dukungan personal.':'Active learning experiences with personal support.',
        'Hormat, rasa ingin tahu, dan kolaborasi.':'Respect, curiosity, and collaboration.',
        'Kami ingin anak merasa dilihat, didengar, dan berani tumbuh menjadi dirinya sendiri setiap hari di sekolah.':'We want every child to feel seen and heard, and to grow into themselves at school each day.',
        'PROGRAM & KURIKULUM':'PROGRAMS & CURRICULUM', 'Belajar dengan':'Learning with', 'rasa ingin tahu':'curiosity',
        'Mengenal fondasi akademik dan ruang eksplorasi anak.':'Explore academic foundations and opportunities for discovery.',
        'Membangun fondasi berpikir melalui aktivitas bermakna.':'Build thinking skills through meaningful activities.',
        'Memberi ruang berekspresi dan percaya diri.':'Make room for expression and confidence.',
        'Belajar bekerja sama lewat permainan dan olahraga.':'Learn to work together through games and sports.',
        'Fondasi akademik':'Academic foundations', 'Bahasa & literasi':'Language & literacy',
        'Matematika dasar':'Basic mathematics', 'Sains dan lingkungan':'Science and the environment',
        'Karakter & sosial':'Character & social skills', 'Komunikasi':'Communication',
        'Kerja sama':'Teamwork', 'Kemandirian':'Independence', 'Kreativitas & fisik':'Creativity & physical development',
        'Seni visual':'Visual arts', 'Musik dan gerak':'Music and movement', 'Olahraga':'Sports',
        'Klub membaca':'Reading club', 'Cerita dan diskusi bersama.':'Stories and group discussions.',
        'Karya kreatif':'Creative projects', 'Eksplorasi seni dan bahan.':'Explore art and materials.',
        'Permainan aktif':'Active play', 'Gerak, koordinasi, dan tim.':'Movement, coordination, and teamwork.',
        'Ruang belajar yang':'Learning spaces that are', 'nyaman dan ceria':'comfortable and joyful',
        'Setiap sudut dirancang untuk belajar, bergerak, dan bermain.':'Every corner is designed for learning, movement, and play.',
        'Ruang kelas':'Classrooms', 'Perpustakaan':'Library', 'Lapangan olahraga':'Sports field',
        'Ruang seni':'Art room', 'Lab komputer':'Computer lab', 'UKS':'School health room',
        'Belajar fokus':'Focused learning', 'Area kelas yang mendukung interaksi aktif.':'Classrooms that encourage active interaction.',
        'Membaca nyaman':'Comfortable reading', 'Sudut bacaan untuk memupuk rasa ingin tahu.':'Reading corners that nurture curiosity.',
        'Bergerak leluasa':'Room to move', 'Ruang aktivitas untuk tubuh yang sehat.':'Activity spaces for healthy bodies.',
        'TIM PENDIDIKAN':'OUR EDUCATORS', 'Orang-orang yang':'The people who', 'mendampingi setiap hari':'support children every day',
        'Kenali pendidik dan tenaga sekolah di balik pengalaman anak.':'Meet the educators and staff who shape each child’s experience.',
        'Melihat tiap anak':'Seeing every child', 'Mengenali kebutuhan dan cara belajar yang berbeda.':'Understanding different needs and learning styles.',
        'Membuat ruang aman':'Creating a safe space', 'Anak bebas bertanya dan berani mencoba.':'Children can ask questions and try new things.',
        'Komunikasi yang terbuka dengan orang tua.':'Open communication with parents.',
        'BERITA & GALERI':'NEWS & GALLERY', 'Cerita seru dari':'Stories from', 'setiap hari belajar':'every school day',
        'KEGIATAN':'ACTIVITIES', 'Belajar di ruang kelas':'Learning in the classroom',
        'Belajar bersama di ruang kelas yang hangat, dengan kesempatan bertanya dan mencoba hal baru.':'Learning together in a welcoming classroom, with opportunities to ask questions and try new things.',
        'KARYA ANAK':'CHILDREN’S WORK', 'Eksplorasi seni bersama':'Exploring art together',
        'Anak-anak mengeksplorasi warna, bahan, dan ide melalui kegiatan seni bersama.':'Children explore colors, materials, and ideas through shared art activities.',
        'KOMUNITAS':'COMMUNITY', 'Kegiatan keluarga sekolah':'School community activities',
        'Keluarga dan sekolah berbagi pengalaman serta mendampingi tumbuh kembang anak.':'Families and school share experiences and support children’s growth.',
        'Pilih berkas':'Choose a file', 'atau jatuhkan di sini':'or drop it here',
        'Belum memilih berkas':'No file selected', 'Belum diunggah':'Not uploaded yet',
        'Supaya mudah dihubungi':'So we can reach you', 'Kontak ini dipakai untuk kabar pendaftaran.':'We’ll use these details for registration updates.',
        'Beberapa detail kecil tentang anak.':'A few more details about the child.',
        'Siapkan empat berkas ini, ya. Foto atau scan harus terbaca jelas dan ukuran masing-masing maksimal 1 MB.':'Please prepare these four documents. Photos or scans must be clear and no larger than 1 MB each.',
        'Pastikan nama dan isi dokumen terlihat jelas sebelum diunggah.':'Make sure the name and document details are clear before uploading.',
        'Pengumuman pendaftaran':'Admission announcements', 'Cari pengumuman':'Search announcements',
        'Periksa hasil seleksi':'Check your admission results', 'Tanggal pengumuman':'Announcement date',
        'Alamat':'Address', 'Jam operasional':'Opening hours', 'Hubungi panitia':'Contact the admissions team',
        'Jadwal & usia':'Schedule & age', 'Berkas & biaya':'Documents & fees', 'Jelas sebelum mengambil keputusan':'Know before you decide',
        'Informasi dan persyaratan disiapkan sebelum formulir dimulai.':'Review the information and requirements before starting the form.',
        'Isi formulir':'Complete the form', 'Unggah dokumen':'Upload documents', 'Kirim & konfirmasi':'Submit & confirm',
        'Akta, KK, KIA, dan pas foto':'Birth certificate, family card, child ID card, and photo',
        'Data anak dan orang tua':'Child and parent information', 'Periksa kelengkapan file':'Check that your files are complete',
        'Simpan nomor pendaftaran':'Save your registration number',
        'Periode pendaftaran:':'Registration period:', 'Usia masuk:':'Admission age:',
        'Kunjungan sekolah: berdasarkan janji':'School visits: by appointment',
        'Akta kelahiran, Kartu Keluarga, KIA, dan pas foto anak':'Birth certificate, family card, child ID card, and child photo',
        'Biaya pendaftaran:':'Registration fee:', 'Rincian resmi tersedia dalam brosur':'Official details are available in the brochure',
        'Simpan nomor ini bersama tanggal lahir anak untuk memantau hasil seleksi.':'Keep this number and your child’s date of birth to check the admission result.',
        'Gunakan nomor pendaftaran dan tanggal lahir anak untuk melihat hasil seleksi.':'Use your registration number and your child’s date of birth to check the result.',
        'Tim penerimaan akan memperbarui hasil setelah proses pemeriksaan selesai.':'The admissions team will update the result after the review is complete.',
        'Gunakan WhatsApp atau email yang tercantum pada halaman Kontak. Layanan tersedia pada hari kerja.':'Contact us by WhatsApp or email listed on the Contact page. We are available on weekdays.',
        'Tersedia kegiatan literasi, seni dan kreativitas, serta olahraga dan eksplorasi. Lihat halaman Program untuk rinciannya.':'We offer literacy, arts and creativity, sports, and exploration activities. Visit Programs for details.',
        'Isi formulir, unggah dokumen, dan simpan nomor pendaftaran. Panitia akan memeriksa kelengkapan dan menghubungi Anda mengenai tahapan berikutnya.':'Complete the form, upload documents, and save your registration number. Our team will review your application and contact you about next steps.',
        'Bisa. Ajukan jadwal kunjungan melalui halaman Kontak. Tim sekolah akan menghubungi Anda untuk konfirmasi.':'Yes. Request a visit on the Contact page. Our team will reach out to confirm.',
        'Usia masuk dalam informasi sekolah adalah 6–7 tahun pada Juli 2027. Konfirmasikan kebutuhan anak kepada panitia.':'The stated entry age is 6–7 years in July 2027. Please confirm your child’s eligibility with the admissions team.',
        'Biaya pendaftaran dalam informasi sekolah adalah Rp250.000. Hubungi panitia untuk rincian biaya pendidikan terbaru.':'The listed registration fee is Rp250,000. Contact the admissions team for current tuition details.',
        'Periode pendaftaran Oktober 2026–Mei 2027. Hubungi panitia untuk memastikan ketersediaan tempat.':'Registration runs from October 2026 to May 2027. Contact the admissions team to check availability.',
        'Siapkan akta kelahiran, Kartu Keluarga, Kartu Identitas Anak (KIA), dan pas foto anak. Akta, KK, dan KIA dapat berupa JPG, PNG, atau PDF; pas foto berupa JPG atau PNG, maksimal 1 MB per berkas.':'Prepare the birth certificate, family card, child ID card, and child photo. Documents may be JPG, PNG, or PDF; the photo must be JPG or PNG. Each file must be no larger than 1 MB.',
        'Mulai perjalanan belajar anak':'Start your child’s learning journey', 'Daftar online':'Register online',
        'Nama lengkap anak':'Child’s full name', 'Tanggal lahir anak':'Child’s date of birth',
        'Hubungi kami':'Contact us', 'Kirim pesan':'Send a message', 'Kirim permintaan':'Send request',
        'Permintaan kunjungan':'Visit request', 'Nama lengkap':'Full name', 'Tanggal kunjungan':'Visit date',
        'Catatan keluarga':'Family notes', 'Pilih tanggal':'Choose a date', 'Kirim permintaan kunjungan':'Request a visit',
        'Pendaftaran siswa baru':'New student registration', 'Informasi sekolah':'School information',
        'Informasi pendaftaran':'Registration information', 'Kontak sekolah':'School contact',
        'Sambutan kepala sekolah':'A message from the principal', 'Profil sekolah':'About our school',
        'Pendaftaran berhasil':'Registration successful', 'Periksa & Kirim':'Review & Submit',
    };

    const normalize = value => value.replace(/\s+/g, ' ').trim();
    const translateText = (root, english) => {
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
            acceptNode(node) {
                const parent = node.parentElement;
                if (!parent || /^(SCRIPT|STYLE|TEXTAREA|INPUT|SELECT|OPTION|NOSCRIPT)$/.test(parent.tagName)) return NodeFilter.FILTER_REJECT;
                return normalize(node.nodeValue) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT;
            },
        });
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(node => {
            if (!node.__originalText) node.__originalText = node.nodeValue;
            const original = normalize(node.__originalText);
            const replacement = english ? translations[original] : original;
            if (!replacement) return;
            const leading = node.__originalText.match(/^\s*/)?.[0] || '';
            const trailing = node.__originalText.match(/\s*$/)?.[0] || '';
            node.nodeValue = `${leading}${replacement}${trailing}`;
        });

        document.querySelectorAll('[placeholder],[aria-label],[title],[data-caption],[alt]').forEach(element => {
            ['placeholder', 'aria-label', 'title', 'data-caption', 'alt'].forEach(attribute => {
                if (!element.hasAttribute(attribute)) return;
                const key = `data-original-${attribute}`;
                if (!element.hasAttribute(key)) element.setAttribute(key, element.getAttribute(attribute));
                const original = element.getAttribute(key);
                if (!english) element.setAttribute(attribute, original);
                else if (translations[normalize(original)]) element.setAttribute(attribute, translations[normalize(original)]);
            });
        });
    };

    const languageButton = document.querySelector('[data-language-toggle]');
    const setLanguage = language => {
        const english = language === 'en';
        document.documentElement.lang = english ? 'en' : 'id';
        document.documentElement.dataset.language = language;
        try { localStorage.setItem('scn-language', language); } catch (error) {}
        translateText(document.body, english);
        if (languageButton) {
            languageButton.textContent = english ? 'ID' : 'EN';
            languageButton.title = english ? 'Bahasa Indonesia' : 'English';
            languageButton.setAttribute('aria-label', english ? 'Ganti ke Bahasa Indonesia' : 'Switch to English');
        }
    };

    let language = 'id';
    try { language = localStorage.getItem('scn-language') || 'id'; } catch (error) {}
    setLanguage(language);
    languageButton?.addEventListener('click', () => setLanguage(document.documentElement.dataset.language === 'en' ? 'id' : 'en'));

    const themeButton = document.querySelector('[data-theme-toggle]');
    const setTheme = theme => {
        document.documentElement.dataset.theme = theme;
        try { localStorage.setItem('scn-theme', theme); } catch (error) {}
        if (themeButton) {
            const dark = theme === 'dark';
            themeButton.innerHTML = `<span aria-hidden="true">${dark ? '☀' : '☾'}</span>`;
            themeButton.title = dark ? 'Mode terang' : 'Mode gelap';
            themeButton.setAttribute('aria-label', dark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap');
        }
    };
    let theme = 'light';
    try { theme = localStorage.getItem('scn-theme') || 'light'; } catch (error) {}
    setTheme(theme);
    themeButton?.addEventListener('click', () => setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark'));
})();
