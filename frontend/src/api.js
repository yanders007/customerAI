import { useEffect } from 'react';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { initTheme } from './api';
import {
  AdminLogin,
  AdminPanel,
  Chat,
  ClientLogin,
  ForgotPassword,
  ProjectSelect,
  ResetPassword,
  SupportConversation,
} from './pages';

export default function App() {
  useEffect(() => { initTheme(); }, []);
  return (
    <BrowserRouter>
      <Routes>
        <Route path="/"                           element={<Navigate to="/login-client" replace/>}/>
        <Route path="/login-client"               element={<ClientLogin/>}/>
        <Route path="/login-admin"                element={<AdminLogin/>}/>
        <Route path="/forgot-password"            element={<ForgotPassword/>}/>
        <Route path="/reset-password"             element={<ResetPassword/>}/>
        <Route path="/projects"                   element={<ProjectSelect/>}/>
        <Route path="/chat"                       element={<Chat/>}/>
        <Route path="/admin"                      element={<AdminPanel/>}/>
        <Route path="/support/conversation/:uuid" element={<SupportConversation/>}/>
        <Route path="*"                           element={<Navigate to="/login-client" replace/>}/>
      </Routes>
    </BrowserRouter>
  );
}
