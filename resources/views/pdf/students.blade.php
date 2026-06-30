<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>Students List</title>

    <style>
        body {
            font-family: DejaVu Sans;
            direction: rtl;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            border: 1px solid black;
            padding: 8px;
            text-align: center;
        }

        h1 {
            text-align: center;
        }
    </style>
</head>
<body>

    <h1>قائمة الطلاب</h1>

    <table>
        <thead>
            <tr>
                <th>الاسم</th>
                <th>الرقم الجامعي</th>
                <th>السنة</th>
                <th>المجموعة</th>
                <th>الرقم الامتحاني</th>
            </tr>
        </thead>

        <tbody>
            @foreach($students as $student)
                <tr>
                    <td>{{ $student->name }}</td>
                    <td>{{ $student->university_id }}</td>
                    <td>{{ $student->year_of_study }}</td>
                    <td>{{ $student->group_number }}</td>
                    <td>{{ $student->exam_number }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>