// Vercel Serverless Function — replaces send-application.php
// Path in your project: /api/send-application.js
// Install:  npm install nodemailer
// Vercel env vars: SMTP_USER, SMTP_PASS (Gmail App Password), TO_EMAIL

const nodemailer = require('nodemailer');

const labels = {
  fullName: 'Full Name',
  motherName: "Mother's Name",
  mobile: 'Mobile Number',
  email: 'Email',
  dob: 'Date of Birth',
  city: 'City',
  address: 'Address',
  pincode: 'Pincode',
  panNumber: 'PAN Number',


};

const esc = (s) =>
  String(s).replace(/[&<>"']/g, (c) =>
    ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])
  );

module.exports = async (req, res) => {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') return res.status(204).end();
  if (req.method !== 'POST') {
    return res.status(405).json({ success: false, message: 'Method not allowed' });
  }

  const data = req.body;
  if (!data || typeof data !== 'object' || !Object.keys(data).length) {
    return res.status(400).json({ success: false, message: 'No data received' });
  }

  const rows = Object.entries(data)
    .map(([key, value]) => {
      const label = labels[key] || key.charAt(0).toUpperCase() + key.slice(1);
      if (typeof value === 'boolean') value = value ? 'Yes' : 'No';
      return `<tr><td style="padding:6px 12px;border:1px solid #ddd;font-weight:bold;">${esc(label)}</td>`
        + `<td style="padding:6px 12px;border:1px solid #ddd;">${esc(value ?? '')}</td></tr>`;
    })
    .join('');

  const received = new Date().toLocaleString('en-IN', { timeZone: 'Asia/Kolkata' });
  const html = `<h2>New Online Application</h2><p>Received: ${received}</p>`
    + `<table style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:14px;">${rows}</table>`;

  const transporter = nodemailer.createTransport({
    service: 'gmail',
    auth: { user: process.env.SMTP_USER, pass: process.env.SMTP_PASS },
  });

  const validEmail = typeof data.email === 'string' && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email);

  try {
    await transporter.sendMail({
      from: `"Online Application" <${process.env.SMTP_USER}>`,
      to: process.env.TO_EMAIL || 'Udaybajaj2211@gmail.com',
      subject: 'New Online Application',
      html,
      ...(validEmail ? { replyTo: data.email } : {}),
    });
    return res.status(200).json({ success: true, message: 'Application details sent' });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ success: false, message: 'Mail could not be sent from the server' });
  }
};