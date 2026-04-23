// Razorpay Donation Button Integration
// Author: JAzz N

document.addEventListener("DOMContentLoaded", function () {

    const donateBtn = document.getElementById("donateBtn");

    if (!donateBtn) return;

    donateBtn.addEventListener("click", function (e) {
        e.preventDefault();

        // 🧾 Get form values (adjust IDs as per your form)
        const name = document.getElementById("donor_name").value;
        const email = document.getElementById("donor_email").value;
        const amount = document.getElementById("donation_amount").value;
        const project = document.getElementById("donation_project").value;

        if (!name || !email || !amount) {
            alert("Please fill all required fields");
            return;
        }

        // Convert amount to paise
        const amountInPaise = parseInt(amount) * 100;

        // ⚡ Create Razorpay Order via AJAX (recommended)
        fetch(ajax_object.ajax_url, {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            },
            body: new URLSearchParams({
                action: "create_razorpay_order",
                name: name,
                email: email,
                amount: amountInPaise,
                project: project
            })
        })
        .then(res => res.json())
        .then(data => {

            if (!data.success) {
                alert("Order creation failed");
                return;
            }

            const order = data.data;

            // 💳 Razorpay Checkout Options
            const options = {
                key: "YOUR_TEST_KEY_ID", // Replace with test key
                amount: order.amount,
                currency: order.currency,
                name: "Donation",
                description: "Support our cause",
                order_id: order.order_id,

                handler: function (response) {

                    // ✅ Send payment details to backend
                    fetch(ajax_object.ajax_url, {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: new URLSearchParams({
                            action: "razorpay_payment",
                            entry_id: order.entry_id,
                            razorpay_payment_id: response.razorpay_payment_id,
                            razorpay_order_id: response.razorpay_order_id,
                            razorpay_signature: response.razorpay_signature
                        })
                    })
                    .then(res => res.json())
                    .then(result => {
                        if (result.success) {
                            alert("Payment successful!");
                            window.location.href = "/thank-you";
                        } else {
                            alert("Payment verification failed");
                        }
                    });

                },

                prefill: {
                    name: name,
                    email: email
                },

                theme: {
                    color: "#3399cc"
                }
            };

            const rzp = new Razorpay(options);
            rzp.open();

        })
        .catch(err => {
            console.error(err);
            alert("Something went wrong");
        });

    });

});
