<?php
require_once 'config.php';
require_once __DIR__ . '/vendor/autoload.php'; // if using Composer

use FPDF\FPDF;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];

    $client = new MongoDB\Client($mongoUri);
    $db = $client->elodge_db;

    $bookings = $db->bookings->find([
        'created_at' => ['$gte' => new MongoDB\BSON\UTCDateTime(strtotime($start) * 1000),
                         '$lte' => new MongoDB\BSON\UTCDateTime(strtotime($end . ' 23:59:59') * 1000)]
    ]);

    class PDF extends FPDF {
        function Header() {
            $this->SetFillColor(86, 28, 36);
            $this->Rect(0, 0, 210, 20, 'F');
            $this->SetTextColor(232, 216, 196);
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, 'E-LODGE SYSTEM REPORT', 0, 1, 'C');
            $this->Ln(10);
        }
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(109, 41, 50);
            $this->Cell(0, 10, 'Page '.$this->PageNo(), 0, 0, 'C');
        }
    }

    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor(86, 28, 36);

    $pdf->Cell(0, 10, "Report Duration: $start to $end", 0, 1);
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(60, 10, 'Guest Name', 1, 0, 'C');
    $pdf->Cell(60, 10, 'Room Type', 1, 0, 'C');
    $pdf->Cell(60, 10, 'Booking Date', 1, 1, 'C');

    $pdf->SetFont('Arial', '', 11);
    foreach ($bookings as $booking) {
        $guest = $booking['guest_name'] ?? 'N/A';
        $room = $booking['room_type'] ?? 'N/A';
        $date = isset($booking['created_at']) ? $booking['created_at']->toDateTime()->format('M d, Y') : 'N/A';
        $pdf->Cell(60, 10, $guest, 1, 0, 'C');
        $pdf->Cell(60, 10, $room, 1, 0, 'C');
        $pdf->Cell(60, 10, $date, 1, 1, 'C');
    }

    $pdf->Output('D', "E-LODGE_Report_$start-$end.pdf");
    exit();
}
?>
