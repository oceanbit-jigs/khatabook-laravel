@extends('emails.email_master')
@section('content')

<table cellpadding='0' cellspacing='0' width='600' align='center'>
    <!-- Header Section -->
    @include('emails.component.header')

    <!-- Content Section -->
    <tr>
        <td style='padding:30px 10px; font-family:Arial, Helvetica, sans-serif; font-size:15px;'>
            <h1>Hello {{ $data['name'] }},</h1>
            <p>
                We received a request to delete your account. To confirm this action, please use the verification code below:
            </p>
            <p style="font-size: 15px; font-weight: bold;">
                Verification Code: <strong>{{ $data['code'] }}</strong>
            </p>
            <p>
                This verification code will expire shortly. For your safety, do not share this code with anyone.
            </p>
            <p>
                If you did not request this account deletion, please contact our support team immediately.
            </p>
            <p>
                Regards,<br>
                The Split Bill Team
            </p>
        </td>
    </tr>

    <!-- Footer Section -->
    @include('emails.component.footer')
</table>
@endsection
