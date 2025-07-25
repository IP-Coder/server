import { useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../services/api";

export default function Login() {
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const navigate = useNavigate();

    const handleLogin = async (e) => {
        e.preventDefault();
        try {
            const res = await api.post("login", { email, password });
            console.log(res.data.token);
            localStorage.setItem("token", res.data.token);
            navigate("/dashboard");
        } catch (err) {
            alert("Login failed");
        }
    };

    return (
        <div className="flex items-center justify-center min-h-screen bg-gray-100">
            <form
                onSubmit={handleLogin}
                className="bg-white p-6 rounded shadow-md w-full max-w-sm"
            >
                <h2 className="text-2xl font-bold mb-4">Login</h2>
                <input
                    type="email"
                    placeholder="Email"
                    className="w-full p-2 mb-3 border rounded"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                />
                <input
                    type="password"
                    placeholder="Password"
                    className="w-full p-2 mb-3 border rounded"
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                />
                <button className="w-full bg-blue-500 text-white py-2 rounded hover:bg-blue-600">
                    Login
                </button>
            </form>
        </div>
    );
}
