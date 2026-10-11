import React from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { BookingProvider } from './context/BookingContext';
import { ReceptionProvider } from './context/ReceptionContext';

// Customer Layout & Pages
import { MainLayout } from './components/layout/MainLayout';
import { HomePage } from './pages/HomePage';
import { RoomsPage } from './pages/RoomsPage';
import { ExperiencesPage } from './pages/ExperiencesPage';

// Receptionist Front Office Layout & Pages
import { AdminLayout } from './components/admin/AdminLayout';
import { DashboardPage } from './pages/admin/DashboardPage';
import { RoomManagementPage } from './pages/admin/RoomManagementPage';
import { PaymentManagementPage } from './pages/admin/PaymentManagementPage';

function App() {
  return (
    <BookingProvider>
      <ReceptionProvider>
        <BrowserRouter>
          <Routes>
            {/* Customer Website Routes */}
            <Route path="/" element={<MainLayout />}>
              <Route index element={<HomePage />} />
              <Route path="rooms" element={<RoomsPage />} />
              <Route path="experiences" element={<ExperiencesPage />} />
            </Route>

            {/* Receptionist Front Office Routes */}
            <Route path="/admin" element={<AdminLayout />}>
              <Route index element={<DashboardPage />} />
              <Route path="rooms" element={<RoomManagementPage />} />
              <Route path="payments" element={<PaymentManagementPage />} />
            </Route>

            {/* Fallback */}
            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </BrowserRouter>
      </ReceptionProvider>
    </BookingProvider>
  );
}

export default App;
