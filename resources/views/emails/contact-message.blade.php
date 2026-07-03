<div style="font-family: Arial, sans-serif; color: #101828; line-height: 1.6;">
    <h1 style="margin-bottom: 12px;">New contact message</h1>
    <p style="margin: 0 0 4px;"><strong>From:</strong> {{ $senderName }} &lt;{{ $senderEmail }}&gt;</p>
    <p style="margin: 0 0 16px;"><strong>Subject:</strong> {{ $subjectLine }}</p>
    <div style="background: #f4f7f5; border: 1px solid #e3ece7; border-radius: 8px; padding: 16px; white-space: pre-wrap;">{{ $body }}</div>
    <p style="margin-top: 16px; color: #667085; font-size: 13px;">Reply directly to this email to respond to {{ $senderName }}.</p>
</div>
