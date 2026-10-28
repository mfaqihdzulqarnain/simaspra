{{--
    Halaman login panel: panel grafis di kiri (desktop) atau latar (ponsel).
    Disisipkan lewat render hook SIMPLE_LAYOUT_START, hanya pada halaman Login.
--}}
@include('partials.panel-grafis', [
    'judul' => 'Satu pintu untuk seluruh aset kampus',
    'teks' => 'Masuk untuk mengelola aset, mengajukan kebutuhan unit, atau meminjam alat laboratorium.',
])

<style>
    /* Form di atas latar pada tablet dan ponsel */
    .fi-simple-main-ctn {
        position: relative;
        z-index: 1;
        min-height: 100vh;
        min-height: 100dvh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1.5rem 1rem;
    }

    .fi-simple-main {
        width: 100%;
        max-width: 28rem;
        border-radius: 1rem;
        box-shadow: 0 24px 64px rgba(0, 0, 0, .35);
    }

    /* Desktop: panel kiri, form kanan */
    @media (min-width: 1024px) {
        .fi-simple-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.15fr) minmax(440px, 1fr);
            align-items: stretch;
            min-height: 100vh;
        }

        .fi-simple-main-ctn {
            grid-column: 2;
            grid-row: 1;
            min-height: 100vh;
            padding: 3rem;
        }

        .fi-simple-main { box-shadow: none; }

        /* Logo sudah tampil di panel kiri */
        .fi-simple-header .fi-logo { display: none; }
    }
</style>