@extends('emails.email_master')
@section('content')

<table cellpadding='0' cellspacing='0' width='600' align='center'>
    <!-- Header Section -->
        @include('emails.component.header')
    <!-- Content Section -->
    <tr>
        <td style='padding:30px 10px; font-family:Arial, Helvetica, sans-serif; font-size:15px;'>
            <h1>Hello {{ $data['name']}},</h1>
            <p>
                A recent login request for your account has been detected. To proceed, please use the verification code below:
            </p>
            <p style="font-size: 15px; font-weight: bold;">
                Verification Code: <strong>{{ $data['code'] }}</strong>
            </p>
            <p>
                For security purposes, this code should remain confidential. If this login request was not initiated by
                you, it is strongly recommended to contact your administrator immediately.
            </p>
            <p>
                Best regards,<br>
                The Split Bill Team
            </p>
    </tr>
    <!-- Footer Section -->
        @include('emails.component.footer')
</table>
@endsection
