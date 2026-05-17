<?php
// index.php
require 'koneksi.php';

// Ambil daftar gejala untuk ditampilkan
$stmtGejala = $pdo->query("SELECT * FROM tabel_gejala");
$daftarGejala = $stmtGejala->fetchAll();

$hasilDiagnosis = [];
$pesanSimpan = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cf_user'])) {
    $inputUser = $_POST['cf_user'];
    $nama = trim($_POST['nama']);
    $kelompok = trim($_POST['kelompok']);

    if (empty($nama) || empty($kelompok)) {
        $pesanSimpan = "<p style='color:red;'>Nama dan Kelompok wajib diisi!</p>";
    } else {
        // 1. Mengambil Aturan Basis Pengetahuan
        $stmtAturan = $pdo->query("
            SELECT a.id_penyakit, p.nama_penyakit, a.id_gejala, a.cf_pakar 
            FROM tabel_aturan a
            JOIN tabel_penyakit p ON a.id_penyakit = p.id_penyakit
        ");
        $semuaAturan = $stmtAturan->fetchAll();

        // Menyusun array aturan
        $basisPengetahuan = [];
        foreach ($semuaAturan as $row) {
            $basisPengetahuan[$row['nama_penyakit']][$row['id_gejala']] = $row['cf_pakar'];
        }

        // 2. Perhitungan Algoritma Certainty Factor
        foreach ($basisPengetahuan as $penyakit => $aturanPenyakit) {
            $cfKombinasi = 0;
            foreach ($aturanPenyakit as $idGejala => $cfPakar) {
                if (isset($inputUser[$idGejala]) && $inputUser[$idGejala] > 0) {
                    $cfUser = (float) $inputUser[$idGejala];
                    
                    // CF(Gejala) = CF(Pakar) * CF(User)
                    $cfGejala = $cfPakar * $cfUser;
                    
                    // CF(Kombinasi) = CF(Lama) + (CF(Baru) * (1 - CF(Lama)))
                    if ($cfKombinasi == 0) {
                        $cfKombinasi = $cfGejala; 
                    } else {
                        $cfKombinasi = $cfKombinasi + ($cfGejala * (1 - $cfKombinasi)); 
                    }
                }
            }
            if ($cfKombinasi > 0) {
                $hasilDiagnosis[$penyakit] = $cfKombinasi * 100;
            }
        }
        
        arsort($hasilDiagnosis); // Urutkan dari persentase terbesar

        // 3. Penyimpanan Data Transaksi
        if (!empty($hasilDiagnosis)) {
            $penyakitTertinggi = array_key_first($hasilDiagnosis);
            $nilaiTertinggi = $hasilDiagnosis[$penyakitTertinggi];

            try {
                $pdo->beginTransaction();

                // Simpan Riwayat Utama
                $sqlRiwayat = "INSERT INTO tabel_riwayat (nama, kelompok, penyakit_terdiagnosis, nilai_kepastian) VALUES (?, ?, ?, ?)";
                $stmtRiwayat = $pdo->prepare($sqlRiwayat);
                $stmtRiwayat->execute([$nama, $kelompok, $penyakitTertinggi, $nilaiTertinggi]);
                
                $idRiwayatBaru = $pdo->lastInsertId();

                // Simpan Detail Gejala
                $sqlDetail = "INSERT INTO tabel_riwayat_detail (id_riwayat, id_gejala, cf_user) VALUES (?, ?, ?)";
                $stmtDetail = $pdo->prepare($sqlDetail);

                foreach ($inputUser as $idGejala => $cfUser) {
                    if ($cfUser > 0) {
                        $stmtDetail->execute([$idRiwayatBaru, $idGejala, $cfUser]);
                    }
                }

                $pdo->commit();
                $pesanSimpan = "<p style='color:green; padding:10px; background:#d4edda; border:1px solid #c3e6cb;'>Diagnosis atas nama <strong>" . htmlspecialchars($nama) . "</strong> berhasil diproses dan disimpan.</p>";

                // Bersihkan variabel agar form siap untuk entri baru, atau Anda bisa membiarkannya
                $_POST = array(); 

            } catch (Exception $e) {
                $pdo->rollBack();
                $pesanSimpan = "<p style='color:red;'>Gagal menyimpan data: " . $e->getMessage() . "</p>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Sistem Pakar Anak</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; background-color: #f4f7f6; }
        .nav { margin-bottom: 20px; }
        .nav a { text-decoration: none; padding: 10px 15px; background: #333; color: white; border-radius: 4px; margin-right: 10px; }
        .container { display: flex; gap: 30px; }
        .form-section, .result-section { background: white; border: 1px solid #ddd; padding: 25px; border-radius: 8px; width: 50%; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        input[type="text"], select { margin-bottom: 15px; padding: 10px; width: 100%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .identitas-box { background: #e9ecef; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 5px solid #0056b3; }
        button { padding: 12px 20px; background: #0056b3; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; width: 100%; }
        button:hover { background: #004494; }
        h2 { color: #333; }
    </style>
</head>
<body>

    <h2>Sistem Pakar Pertolongan Pertama Pada Anak</h2>
    
    <div class="nav">
        <a href="index.php">Formulir Diagnosis</a>
        <a href="riwayat.php" style="background: #28a745;">Lihat Data Riwayat</a>
    </div>

    <?= $pesanSimpan ?>

    <div class="container">
        <!-- Panel Form -->
        <div class="form-section">
            <form method="POST" action="">
                
                <div class="identitas-box">
                    <label><strong>Nama Anak:</strong></label>
                    <input type="text" name="nama" required placeholder="Masukkan nama..." value="<?= isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : '' ?>">

                    <label><strong>Kelompok/Kelas:</strong></label>
                    <input type="text" name="kelompok" required placeholder="Contoh: Kelompok 3 / TRPL-C" value="<?= isset($_POST['kelompok']) ? htmlspecialchars($_POST['kelompok']) : '' ?>">
                </div>

                <p><strong>Pilih tingkat keyakinan gejala:</strong></p>
                <?php foreach ($daftarGejala as $gejala): ?>
                    <label><?= htmlspecialchars($gejala['id_gejala']) ?> - <?= htmlspecialchars($gejala['nama_gejala']) ?></label>
                    <select name="cf_user[<?= $gejala['id_gejala'] ?>]">
                        <option value="0">Tidak (0.0)</option>
                        <option value="0.4">Mungkin (0.4)</option>
                        <option value="0.6">Kemungkinan Besar (0.6)</option>
                        <option value="0.8">Yakin (0.8)</option>
                        <option value="1.0">Sangat Yakin (1.0)</option>
                    </select>
                <?php endforeach; ?>
                <br><br>
                <button type="submit">Proses & Simpan Diagnosis</button>
            </form>
        </div>

        <!-- Panel Hasil -->
        <div class="result-section">
            <h3>Hasil Analisis Sistem</h3>
            <?php if (!empty($hasilDiagnosis)): ?>
                <ul style="font-size: 1.1em;">
                    <?php foreach ($hasilDiagnosis as $namaPenyakit => $persentase): ?>
                        <li>
                            <strong><?= htmlspecialchars($namaPenyakit) ?></strong>: 
                            <?= number_format($persentase, 2) ?>%
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div style="background: #fff3cd; padding: 15px; border-left: 5px solid #ffc107; margin-top: 20px;">
                    <p style="margin: 0;"><em>Berdasarkan algoritma Certainty Factor, sistem menyimpulkan penyakit yang paling memungkinkan adalah: <br><strong style="font-size: 1.4em; color: #d9534f;"><?= array_key_first($hasilDiagnosis) ?></strong> (<strong><?= number_format($hasilDiagnosis[array_key_first($hasilDiagnosis)], 2) ?>%</strong>).</em></p>
                </div>
            <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($pesanSimpan)): ?>
                <p>Informasi gejala yang diberikan tidak mencukupi untuk menarik kesimpulan. Silakan isi kembali.</p>
            <?php else: ?>
                <p style="color: #666;">Isi formulir identitas dan tingkat keyakinan gejala di sebelah kiri. Sistem akan mengalkulasi probabilitas penyakit secara otomatis.</p>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>