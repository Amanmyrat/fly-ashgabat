<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eTicket Receipt</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: linear-gradient(to bottom, #e0f7fa, #ffffff);
        }
        .container {
            width: 800px;
            margin: auto;
            border: 1px solid #ccc;
            padding: 20px;
            background: transparent;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: left;
            display: flex;
            align-items: center;
        }
        .header img {
            height: 50px;
            margin-right: 20px;
        }
        .header h2 {
            margin: 0;
            color: red;
        }
        .header p {
            margin: 5px 0;
        }
        .section {
            border: 1px solid #ccc;
            padding: 10px;
            margin-top: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: transparent;
            border: 1px solid #ccc;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
            background: transparent;
        }
        .bold {
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <img src="data:image/png;base64,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" alt="Airline Logo">
        <div>
            <h2>REDFERN TRAVEL LIMITED</h2>
            <p>Phone: 0330 0082000</p>
            <p>Email: corporate@redfern-travel.com</p>
        </div>
    </div>

    <div class="section">
        <h3>eTicket Receipt</h3>
        <p><strong>Prepared For:</strong> WOLFFE/WALTER JAMES MR</p>
        <table>
            <tr><td class="bold">Reservation Code</td><td>MHCCLO</td></tr>
            <tr><td class="bold">Issue Date</td><td>16May17</td></tr>
            <tr><td class="bold">Ticket Number</td><td>0821391336895</td></tr>
            <tr><td class="bold">Issuing Airline</td><td>BRUSSELS AIRLINES</td></tr>
            <tr><td class="bold">Issuing Agent</td><td>REDFERN TRAVEL LIMITED/AAT</td></tr>
            <tr><td class="bold">Issuing Agent Location</td><td>YORK UK</td></tr>
            <tr><td class="bold">IATA Number</td><td>91270432</td></tr>
            <tr><td class="bold">Customer Number</td><td>03COPF</td></tr>
        </table>
    </div>

    <div class="section">
        <h3>Itinerary Details</h3>
        <table>
            <tr>
                <th>Travel Date</th>
                <th>Airline</th>
                <th>Departure</th>
                <th>Arrival</th>
                <th>Other Notes</th>
            </tr>
            <tr>
                <td>29May17</td>
                <td>BRUSSELS AIRLINES SN 2064</td>
                <td>Edinburgh, UK<br>Time: 16:00</td>
                <td>Brussels, Belgium<br>Time: 18:35</td>
                <td>Class: Economy, Baggage: 1PC, Booking Status: Confirmed</td>
            </tr>
            <tr>
                <td>30May17</td>
                <td>BRUSSELS AIRLINES SN 2065</td>
                <td>Brussels, Belgium<br>Time: 21:15</td>
                <td>Edinburgh, UK<br>Time: 22:00</td>
                <td>Class: Shuttle Service, Baggage: 1PC, Booking Status: Confirmed</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3>Payment/Fare Details</h3>
        <table>
            <tr><td class="bold">Form of Payment</td><td>Miscellaneous Form of Payment</td></tr>
            <tr><td class="bold">Endorsement / Restrictions</td><td>Free Changes/Any SN Flight</td></tr>
            <tr><td class="bold">Fare Calculation Line</td><td>EDI SN BRU208.60SN EDI193.36NUC401.96END ROEO.819712</td></tr>
            <tr><td class="bold">Fare</td><td>GBP 329.00</td></tr>
            <tr><td class="bold">Taxes/Fees/Carrier-Imposed Charges</td><td>GBP 13.00 (Air Passenger Duty), GBP 14.94 (Passenger Service Charge)</td></tr>
        </table>
    </div>
</div>
</body>
</html>
