<?php
// riwayat.php
require 'koneksi.php';

// Mengambil seluruh data riwayat dari yang terbaru
$stmtRiwayat = $pdo->query("SELECT * FROM tabel_riwayat ORDER BY tanggal_diagnosis DESC");
$dataRiwayat = $stmtRiwayat->fetchAll();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Data Riwayat Diagnosis</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; line-height: 1.6; background-color: #f4f7f6; }
        .nav { margin-bottom: 20px; }
        .nav a { text-decoration: none; padding: 10px 15px; background: #333; color: white; border-radius: 4px; margin-right: 10px; }
        .container { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #0056b3; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
    </style>
</head>
<body>

    <h2>Rekam Data Diagnosis Sistem Pakar</h2>

    <div class="nav">
        <a href="index.php">Formulir Diagnosis</a>
        <a href="riwayat.php" style="background: #28a745;">Lihat Data Riwayat</a>
    </div>

    <div class="container">
        <table>
            <thead>
                <tr>
                    <th>No</th>
                    <th>Waktu Pemeriksaan</th>
                    <th>Nama Anak</th>
                    <th>Kelompok</th>
                    <th>Hasil Penyakit</th>
                    <th>Tingkat Kepastian (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($dataRiwayat) > 0): ?>
                    <?php $no = 1; foreach ($dataRiwayat as $baris): ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= date('d-m-Y H:i', strtotime($baris['tanggal_diagnosis'])) ?></td>
                            <td><?= htmlspecialchars($baris['nama']) ?></td>
                            <td><?= htmlspecialchars($baris['kelompok']) ?></td>
                            <td style="font-weight:bold; color:#d9534f;"><?= htmlspecialchars($baris['penyakit_terdiagnosis']) ?></td>
                            <td><?= number_format($baris['nilai_kepastian'], 2) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">Belum ada data riwayat diagnosis yang tersimpan.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</body>
</html>