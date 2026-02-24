/**
 * TeamCraft WebRTC Signaling Server
 * Handles signaling for live streaming between broadcaster and viewers.
 * Each "room" = one team's stream (identified by teamId).
 */

const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const cors = require('cors');

const app = express();
const server = http.createServer(app);

// Allow connections from Symfony frontend (adjust origin in production)
const io = new Server(server, {
    cors: {
        origin: '*',
        methods: ['GET', 'POST']
    }
});

// Store broadcaster socket ID per room: { teamId: socketId }
const broadcasters = {};

// Store viewer count per room: { teamId: Set<socketId> }
const viewers = {};

app.use(cors());
app.use(express.json());

// Health check endpoint
app.get('/health', (req, res) => {
    const rooms = {};
    for (const teamId of Object.keys(broadcasters)) {
        rooms[teamId] = {
            broadcaster: broadcasters[teamId] || null,
            viewerCount: viewers[teamId] ? viewers[teamId].size : 0
        };
    }
    res.json({ status: 'ok', rooms });
});

// Status endpoint for a specific room
app.get('/room/:teamId/status', (req, res) => {
    const { teamId } = req.params;
    res.json({
        isLive: !!broadcasters[teamId],
        viewerCount: viewers[teamId] ? viewers[teamId].size : 0
    });
});

// ─── Socket.io ─────────────────────────────────────────────────────────────

io.on('connection', (socket) => {
    console.log(`[${new Date().toISOString()}] Socket connected: ${socket.id}`);

    // ── Broadcaster joins a room ──────────────────────────────────────────
    socket.on('broadcaster', ({ teamId }) => {
        console.log(`[BROADCASTER] Team ${teamId} → socket ${socket.id}`);

        // If there's already a broadcaster, disconnect them
        if (broadcasters[teamId] && broadcasters[teamId] !== socket.id) {
            const oldBroadcaster = io.sockets.sockets.get(broadcasters[teamId]);
            if (oldBroadcaster) {
                oldBroadcaster.emit('kicked', { reason: 'Another broadcaster started streaming.' });
            }
        }

        broadcasters[teamId] = socket.id;
        socket.join(`room-${teamId}`);
        socket.data.teamId = teamId;
        socket.data.role = 'broadcaster';

        // Notify all existing viewers in the room that a broadcaster is ready
        socket.to(`room-${teamId}`).emit('broadcaster-ready');

        // Send current viewer count to broadcaster
        const id = String(teamId);
        const initialCount = viewers[id] ? viewers[id].size : 0;
        console.log(`[BROADCASTER] Sending initial viewer count ${initialCount} to new broadcaster ${socket.id} for team ${id}.`);
        socket.emit('viewer-count', { count: initialCount });

        console.log(`[BROADCASTER] Room ${id} is now LIVE`);
    });

    // ── Viewer joins a room ───────────────────────────────────────────────
    socket.on('viewer', ({ teamId }) => {
        const id = String(teamId);
        console.log(`[VIEWER] Team ${id} → socket ${socket.id}`);

        socket.join(`room-${id}`);
        socket.data.teamId = id;
        socket.data.role = 'viewer';

        if (!viewers[id]) viewers[id] = new Set();
        viewers[id].add(socket.id);

        const count = viewers[id].size;

        // Tell viewer if broadcaster is already live
        if (broadcasters[id]) {
            console.log(`[VIEWER] Broadcaster is live for team ${id}, notifying viewer ${socket.id} and broadcaster ${broadcasters[id]} of new viewer.`);
            socket.emit('broadcaster-ready');
            // Notify broadcaster about the new viewer so it sends an offer
            io.to(broadcasters[id]).emit('viewer-joined', { viewerId: socket.id });
        } else {
            console.log(`[VIEWER] Broadcaster NOT live for team ${id}, notifying viewer ${socket.id} to wait.`);
            socket.emit('waiting');
        }

        // Update viewer count for everyone in the room
        console.log(`[VIEWER] Room ${id} now has ${count} viewer(s). Emitting viewer-count to room-${id}.`);
        io.to(`room-${id}`).emit('viewer-count', { count });
    });

    // ── WebRTC Offer (broadcaster → specific viewer) ─────────────────────
    socket.on('offer', ({ to, offer }) => {
        console.log(`[OFFER] ${socket.id} → ${to}`);
        io.to(to).emit('offer', { from: socket.id, offer });
    });

    // ── WebRTC Answer (viewer → broadcaster) ─────────────────────────────
    socket.on('answer', ({ to, answer }) => {
        console.log(`[ANSWER] ${socket.id} → ${to}`);
        io.to(to).emit('answer', { from: socket.id, answer });
    });

    // ── ICE Candidate (bidirectional) ─────────────────────────────────────
    socket.on('candidate', ({ to, candidate }) => {
        io.to(to).emit('candidate', { from: socket.id, candidate });
    });

    // ── Broadcaster stops streaming ───────────────────────────────────────
    socket.on('stop-stream', ({ teamId }) => {
        console.log(`[STOP] Broadcaster stopped stream for team ${teamId}`);
        if (broadcasters[teamId] === socket.id) {
            delete broadcasters[teamId];
        }
        socket.to(`room-${teamId}`).emit('stream-ended');
    });

    // ── Disconnect ────────────────────────────────────────────────────────
    socket.on('disconnect', () => {
        const { teamId, role } = socket.data;
        if (!teamId) return;

        console.log(`[DISCONNECT] ${role} disconnected from team ${teamId}`);

        if (role === 'broadcaster' && broadcasters[teamId] === socket.id) {
            delete broadcasters[teamId];
            // Notify all viewers that the stream ended
            socket.to(`room-${teamId}`).emit('stream-ended');
            console.log(`[STREAM ENDED] Team ${teamId}`);
        }

        if (role === 'viewer' && viewers[teamId]) {
            viewers[teamId].delete(socket.id);
            const count = viewers[teamId].size;
            io.to(`room-${teamId}`).emit('viewer-count', { count });

            // Notify broadcaster that a viewer left (so it can clean up peer connections)
            if (broadcasters[teamId]) {
                io.to(broadcasters[teamId]).emit('viewer-disconnected', { viewerId: socket.id });
            }
        }
    });
});

const PORT = process.env.PORT || 3001;
server.listen(PORT, () => {
    console.log(`\n🚀 TeamCraft Signaling Server running on port ${PORT}`);
    console.log(`   Health check: http://localhost:${PORT}/health\n`);
});
