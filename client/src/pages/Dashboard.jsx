import { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import api from "../services/api";
import MarketsList from "../components/MarketsList";

export default function Dashboard() {
    const navigate = useNavigate();
    const [user, setUser] = useState(null); // optional: fetch user info

    useEffect(() => {
        const token = localStorage.getItem("token");
        if (!token) {
            navigate("/login");
        }
    }, [navigate]);

    const handleLogout = () => {
        localStorage.removeItem("token");
        navigate("/login");
    };

    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-100">
            <div className="bg-white shadow-lg rounded-lg p-8 w-full max-w-md text-center">
                <h1 className="text-3xl font-bold mb-4">Dashboard</h1>
                {user ? (
                    <p className="text-gray-700 mb-4">
                        Welcome, {user.name || user.email}!
                    </p>
                ) : (
                    <p className="text-gray-500 mb-4">Loading user info...</p>
                )}
                <MarketsList />
                <button
                    onClick={handleLogout}
                    className="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600"
                >
                    Logout
                </button>
            </div>
        </div>
    );
}
