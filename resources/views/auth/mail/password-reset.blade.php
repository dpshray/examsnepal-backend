<style>
    .cardStyle,
    input {
        border-radius: 4px;
    }
    .mainDiv {
        display: flex;
        min-height: 100%;
        align-items: center;
        justify-content: center;
        background-color: #f9f9f9;
        font-family: "Open Sans", sans-serif;
    }
    .cardStyle {
        width: 500px;
        border-color: #fff;
        background: #fff;
        padding: 36px 0;
        margin: 30px 0;
        box-shadow: 0 0 2px 0 rgba(0, 0, 0, 0.25);
    }
    #signupLogo {
        max-height: 100px;
        margin: auto;
        display: flex;
        flex-direction: column;
    }
    .formTitle {
        font-weight: 600;
        margin-top: 20px;
        color: #2f2d3b;
        text-align: center;
    }
    .inputLabel {
        font-size: 12px;
        color: #555;
        margin-bottom: 6px;
        margin-top: 24px;
    }
    .inputDiv {
        width: 70%;
        display: flex;
        flex-direction: column;
        margin: auto;
    }
    input {
        height: 40px;
        font-size: 16px;
        border: 1px solid #ccc;
        padding: 0 11px;
    }
    input:disabled {
        cursor: not-allowed;
        border: 1px solid #eee;
    }
    .buttonWrapper {
        margin-top: 40px;
    }
    .submitButton {
        width: 70%;
        height: 40px;
        margin: auto;
        display: block;
        color: #fff;
        background-color: #065492;
        border-color: #065492;
        text-shadow: 0 -1px 0 rgba(0, 0, 0, 0.12);
        box-shadow: 0 2px 0 rgba(0, 0, 0, 0.035);
        border-radius: 4px;
        font-size: 14px;
        cursor: pointer;
    }
    .submitButton:disabled,
    button[disabled] {
        border: 1px solid #ccc;
        background-color: #ccc;
        color: #666;
    }
    #loader {
        position: absolute;
        z-index: 1;
        margin: -2px 0 0 10px;
        border: 4px solid #f3f3f3;
        border-radius: 50%;
        border-top: 4px solid #666;
        width: 14px;
        height: 14px;
        -webkit-animation: 2s linear infinite spin;
        animation: 2s linear infinite spin;
    }
    @keyframes spin {
        0% {
            transform: rotate(0);
        }
        100% {
            transform: rotate(360deg);
        }
    }
    #show_password_checkbox{
        display: flex;
        align-items: center;
        justify-content: end;
        margin: 20px 75px -30px 0px;
    }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css">

<div class="mainDiv">
    <div class="cardStyle">
        <form
            action="{{ route('password.reset', $token) }}"
            method="post"
            name="signupForm"
            id="signupForm"
        >
            <img src="" id="signupLogo" />
            <h2 class="formTitle">
                You are only one step a way from your new password, recover your
                password now.
            </h2>
            <div class="inputDiv">
                <label class="inputLabel" for="email">Email</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    required
                />
            </div>
            <div class="inputDiv">
                <label class="inputLabel" for="password">New Password</label>
                <input type="password" id="password" name="password" required />
            </div>
            <div class="inputDiv">
                <label class="inputLabel" for="confirmPassword"
                    >Confirm Password</label
                >
                <input
                    type="password"
                    id="confirmPassword"
                    name="password_confirmation"
                    required
                />
            </div>
            <div id="show_password_checkbox">
                <div>
                    <input type="checkbox" onclick="togglePasswordVisibility(this)" id="password_visibility_checkbox">
                </div>
                <label for="password_visibility_checkbox">
                    show password
                </label>
            </div>
            <div class="buttonWrapper">
                <button
                    type="button"
                    id="submitButton"
                    onclick="validateSignupForm(this)"
                    class="submitButton pure-button pure-button-primary"
                >
                    <span>Continue</span>
                    <span id="loader"></span>
                </button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>
<script>
    var password = document.getElementById("password"), confirm_password = document.getElementById("confirmPassword");

    document.getElementById("signupLogo").src =
        "https://s3-us-west-2.amazonaws.com/shipsy-public-assets/shipsy/SHIPSY_LOGO_BIRD_BLUE.png";

    enableSubmitButton();

    function togglePasswordVisibility(e) {
        if (e.checked && (password.type === "password" && confirm_password.type === "password")) {
            password.type = confirm_password.type = "text";
        } else {
            password.type = confirm_password.type = "password";
        }
    }

    function validatePassword() {
        console.log([password.value, confirm_password.value]);
        if (password.value != confirm_password.value) {
            toastr.error("Passwords Don't Match");
            return false;
        } else {
            confirm_password.setCustomValidity("");
            return true;
        }
    }

    function enableSubmitButton() {
        document.getElementById("submitButton").disabled = false;
        document.getElementById("loader").style.display = "none";
    }

    function disableSubmitButton() {
        document.getElementById("submitButton").disabled = true;
        document.getElementById("loader").style.display = "unset";
    }

    function validateSignupForm(e) {
        var form = document.getElementById("signupForm");

        for (var i = 0; i < form.elements.length; i++) {
            if (
                form.elements[i].value === "" &&
                form.elements[i].hasAttribute("required")
            ) {
                toastr.error("There are some required fields!");
                return false;
            }
        }

        if (!validatePassword()) {
            return false;
        }

        onSignup(e);
    }

    function onSignup(e) {
        disableSubmitButton();
        const form_el = e.closest('form');
        let form_data = new FormData(form_el);
        let entries = Object.fromEntries(form_data.entries());
        const body = JSON.stringify(entries);
        const url = form_el.getAttribute('action');
        fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
        },
        body
    }).then((response) => {
        if (response.ok) {
            return response.json();
        }
        return response.json().then(errData => {
            throw new Error(errData.message || 'An error occurred');
        });
    }).then(function (data) {
        toastr.success(data.message);
        return true;
    }).catch(error => {
        toastr.error(error);
        return false;
    })
    .finally(() => enableSubmitButton());

    }
</script>
