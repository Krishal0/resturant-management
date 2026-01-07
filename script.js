function generateBill() {
    const table = document.getElementById('tableSelect').value;
    const amount = document.getElementById('amount').value;
    document.getElementById('billResult').innerText = `Bill for Table ${table}: $${amount}`;
}
console.log("Dashboard loaded");
