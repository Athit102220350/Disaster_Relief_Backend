<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <style>
    body {
      font-family: DejaVu Sans, Arial, sans-serif;
      font-size: 12px;
      color: #111111;
    }
    h1 {
      font-size: 18px;
      margin-bottom: 6px;
    }
    .meta {
      margin-bottom: 12px;
      color: #555555;
    }
    table {
      width: 100%;
      border-collapse: collapse;
    }
    th, td {
      border: 1px solid #dddddd;
      padding: 6px;
      text-align: left;
    }
    th {
      background: #f4f4f4;
    }
  </style>
</head>
<body>
  <h1>{{ $title }}</h1>
  <div class="meta">Generated at: {{ $generated_at }}</div>
  <table>
    <thead>
      <tr>
        @foreach ($headers as $header)
          <th>{{ $header }}</th>
        @endforeach
      </tr>
    </thead>
    <tbody>
      @foreach ($rows as $row)
        <tr>
          @foreach ($row as $cell)
            <td>{{ $cell }}</td>
          @endforeach
        </tr>
      @endforeach
    </tbody>
  </table>
</body>
</html>
