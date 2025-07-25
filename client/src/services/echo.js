import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

export default new Echo({
    broadcaster: "pusher",
    key: "your-key",
    cluster: "mt1",
    forceTLS: true,
});
